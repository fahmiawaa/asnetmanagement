<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\IsolationLog;
use App\Models\MikrotikDevice;
use App\Models\PppoeAccount;
use App\Services\AuditLogService;
use App\Services\MikrotikConnectionService;
use App\Services\WhatsAppLinkService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExecuteApprovedIsolation extends Command
{
    protected $signature = 'billing:execute-approved
                            {deviceId : ID perangkat MikroTik}
                            {--execute : Jalankan tindakan nyata}
                            {--confirm= : Konfirmasi wajib ISOLATE}
                            {--limit= : Maksimum pelanggan dalam satu batch}';

    protected $description = 'Memproses kandidat isolir approved secara bertahap dan terverifikasi';

    public function handle(
        MikrotikConnectionService $connectionService,
        WhatsAppLinkService $whatsapp,
        AuditLogService $auditLog
    ): int {
        $deviceId = (int) $this->argument('deviceId');
        $executeRealAction = (bool) $this->option('execute');

        if ($executeRealAction && ! config('mikrotik.allow_real_actions')) {
            $this->error('Tindakan nyata sedang dikunci oleh konfigurasi aplikasi.');

            return self::FAILURE;
        }

        if ($executeRealAction && $this->option('confirm') !== 'ISOLATE') {
            $this->error('Tindakan nyata membutuhkan --confirm=ISOLATE.');

            return self::FAILURE;
        }

        $device = MikrotikDevice::find($deviceId);

        if (! $device) {
            $this->error("Perangkat MikroTik dengan ID {$deviceId} tidak ditemukan.");

            return self::FAILURE;
        }

        $configuredLimit = (int) config('mikrotik.isolation_batch_size', 25);
        $requestedLimit = (int) ($this->option('limit') ?: $configuredLimit);
        $limit = max(1, min(100, $requestedLimit));
        $lock = Cache::lock("asnet:isolation:device:{$device->id}", 600);
        $result = $lock->get(fn (): int => $this->processDevice(
            $device,
            $limit,
            $executeRealAction,
            $connectionService,
            $whatsapp,
            $auditLog
        ));

        if ($result === false) {
            $this->error('Batch isolir perangkat ini sedang diproses oleh proses lain.');

            return self::FAILURE;
        }

        return $result;
    }

    private function processDevice(
        MikrotikDevice $device,
        int $limit,
        bool $executeRealAction,
        MikrotikConnectionService $connectionService,
        WhatsAppLinkService $whatsapp,
        AuditLogService $auditLog
    ): int {
        $this->recoverStaleClaims($device);

        if (! $executeRealAction) {
            return $this->simulateBatch($device, $limit, $connectionService);
        }

        $candidateIds = $this->candidateQuery($device)
            ->limit($limit)
            ->pluck('isolation_logs.id');
        $claimedLogs = collect();

        foreach ($candidateIds as $candidateId) {
            $claimed = $this->claimCandidate((int) $candidateId);

            if ($claimed) {
                $claimedLogs->push($claimed);
            }
        }

        if ($claimedLogs->isEmpty()) {
            $this->info('Tidak ada kandidat isolir approved yang siap diproses.');

            return self::SUCCESS;
        }

        try {
            $results = $connectionService->isolatePppoeBatch(
                $device,
                $claimedLogs
                    ->pluck('customer.pppoeAccount.username')
                    ->filter()
                    ->values()
                    ->all(),
                (int) config('mikrotik.isolation_failure_threshold', 3)
            );
            $device->update([
                'connection_status' => 'connected',
                'last_sync_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $device->update(['connection_status' => 'failed']);

            foreach ($claimedLogs as $log) {
                $this->recordFailure(
                    $log,
                    'Koneksi atau pembacaan batch MikroTik gagal: '.$exception->getMessage()
                );
            }

            report($exception);
            $this->error('Batch isolir gagal terhubung atau memverifikasi MikroTik.');

            return self::FAILURE;
        }

        $rows = [];
        $executedCount = 0;
        $failedCount = 0;

        foreach ($claimedLogs as $log) {
            $customer = $log->customer;
            $pppoeAccount = $customer?->pppoeAccount;
            $username = $pppoeAccount?->username;
            $result = $username ? ($results[$username] ?? null) : null;

            if (! $username || ! $result) {
                $failedCount++;
                $message = 'Hasil batch tidak ditemukan untuk mapping PPPoE pelanggan.';
                $this->recordFailure($log, $message);
                $rows[] = [$customer?->customer_code ?? '-', $username ?? '-', 'FAILED - '.$message];

                continue;
            }

            if ($result['status'] !== 'executed') {
                $failedCount++;
                $message = $result['error'] ?? 'Eksekusi batch tidak berhasil.';
                $this->recordFailure($log, $message);
                $rows[] = [
                    $customer?->customer_code ?? '-',
                    $username,
                    strtoupper($result['status']).' - '.$message,
                ];

                continue;
            }

            $finalStatus = $this->recordSuccess($log, $whatsapp);
            $executedCount++;
            $rows[] = [
                $customer?->customer_code ?? '-',
                $username,
                $finalStatus,
            ];
            $auditLog->record(
                'isolation.executed',
                $log->fresh(),
                'Auto-isolir berhasil dijalankan dan diverifikasi pada MikroTik.',
                [
                    'device_id' => $device->id,
                    'disconnected_sessions' => $result['disconnected'],
                ]
            );
        }

        $this->table(['Kode Customer', 'Username PPPoE', 'Hasil'], $rows);
        $this->line("Berhasil: {$executedCount}");
        $this->line("Gagal/ditunda: {$failedCount}");
        $this->line("Batas batch: {$limit}");

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function simulateBatch(
        MikrotikDevice $device,
        int $limit,
        MikrotikConnectionService $connectionService
    ): int {
        $logs = $this->candidateQuery($device)
            ->with(['customer.pppoeAccount'])
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            $this->info('Tidak ada kandidat isolir approved yang siap diproses.');

            return self::SUCCESS;
        }

        try {
            $accounts = collect($connectionService->getPppoeSecrets($device))
                ->filter(fn (array $account): bool => ! empty($account['name']))
                ->keyBy('name');
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Simulasi gagal membaca akun PPPoE dari MikroTik.');

            return self::FAILURE;
        }

        $rows = $logs->map(function (IsolationLog $log) use ($accounts): array {
            $customer = $log->customer;
            $username = $customer?->pppoeAccount?->username;
            $account = $username ? $accounts->get($username) : null;

            return [
                $customer?->customer_code ?? '-',
                $username ?? '-',
                $account ? 'READY - Akan diisolir' : 'BLOCKED - Akun tidak ditemukan',
            ];
        })->all();

        $this->table(['Kode Customer', 'Username PPPoE', 'Hasil'], $rows);
        $this->warn('Mode simulasi aktif. Tidak ada perubahan pada MikroTik atau database.');

        return self::SUCCESS;
    }

    private function candidateQuery(MikrotikDevice $device): Builder
    {
        return IsolationLog::query()
            ->where('action', 'isolate')
            ->where('execution_mode', 'simulation')
            ->where('approval_status', 'approved')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->whereHas('customer.pppoeAccount', function (Builder $query) use ($device): void {
                $query->where('mikrotik_device_id', $device->id);
            })
            ->orderBy('id');
    }

    private function claimCandidate(int $logId): ?IsolationLog
    {
        return DB::transaction(function () use ($logId): ?IsolationLog {
            $log = IsolationLog::query()
                ->lockForUpdate()
                ->find($logId);

            if (
                ! $log
                || $log->approval_status !== 'approved'
                || $log->execution_mode !== 'simulation'
            ) {
                return null;
            }

            $invoice = Invoice::query()
                ->lockForUpdate()
                ->find($log->invoice_id);
            $customer = Customer::query()
                ->lockForUpdate()
                ->find($log->customer_id);

            if (
                ! $invoice
                || $invoice->status !== 'overdue'
                || ! $customer
                || in_array($customer->status, ['isolated', 'inactive'], true)
            ) {
                $log->update([
                    'approval_status' => 'rejected',
                    'last_error' => 'Kondisi billing berubah sebelum isolir dijalankan.',
                    'notes' => 'Kandidat isolasi dibatalkan karena invoice atau status pelanggan berubah.',
                ]);

                return null;
            }

            $log->update([
                'approval_status' => 'processing',
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
                'next_retry_at' => null,
                'last_error' => null,
            ]);

            $pppoeAccount = PppoeAccount::query()
                ->whereKey($customer->pppoe_account_id)
                ->where('is_present', true)
                ->first();

            if (! $pppoeAccount) {
                $this->applyRetryState(
                    $log,
                    'Mapping PPPoE tidak tersedia atau belum tersinkron.'
                );

                return null;
            }

            return $log->fresh([
                'invoice',
                'customer.pppoeAccount',
            ]);
        }, 3);
    }

    private function recordSuccess(
        IsolationLog $log,
        WhatsAppLinkService $whatsapp
    ): string {
        return DB::transaction(function () use ($log, $whatsapp): string {
            $lockedLog = IsolationLog::query()
                ->lockForUpdate()
                ->findOrFail($log->id);
            $invoice = Invoice::query()
                ->lockForUpdate()
                ->find($lockedLog->invoice_id);
            $customer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($lockedLog->customer_id);
            $pppoeAccount = PppoeAccount::query()
                ->lockForUpdate()
                ->findOrFail($customer->pppoe_account_id);

            $pppoeAccount->update([
                'disabled' => true,
                'is_present' => true,
                'last_synced_at' => now(),
            ]);
            $customer->update(['status' => 'isolated']);

            $billingChanged = ! $invoice || $invoice->status !== 'overdue';
            $message = $billingChanged
                ? 'Akun terisolir saat kondisi billing berubah; restore otomatis dimasukkan ke antrean.'
                : 'Akun PPPoE berhasil dinonaktifkan, sesi diputus, dan hasil diverifikasi.';
            $whatsappUrl = $whatsapp->link(
                $customer->whatsapp_number,
                'Layanan internet '.$customer->name.' sedang diisolasi karena invoice belum dilunasi.'
            );

            $lockedLog->update([
                'approval_status' => 'executed',
                'execution_mode' => 'real',
                'executed_at' => now(),
                'next_retry_at' => null,
                'last_error' => null,
                'notes' => $message.($whatsappUrl ? ' Link WhatsApp: '.$whatsappUrl : ''),
            ]);

            if ($billingChanged) {
                IsolationLog::firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'invoice_id' => $lockedLog->invoice_id,
                        'action' => 'restore',
                    ],
                    [
                        'old_status' => 'isolated',
                        'new_status' => 'active',
                        'execution_mode' => 'simulation',
                        'approval_status' => 'pending',
                        'reason' => 'Billing berubah saat batch isolir diproses.',
                        'notes' => 'Restore pengaman dibuat otomatis.',
                    ]
                );

                return 'EXECUTED - Restore pengaman dijadwalkan';
            }

            return 'EXECUTED - Disabled dan sesi terputus';
        }, 3);
    }

    private function recordFailure(IsolationLog $log, string $message): void
    {
        DB::transaction(function () use ($log, $message): void {
            $lockedLog = IsolationLog::query()
                ->lockForUpdate()
                ->find($log->id);

            if (! $lockedLog || $lockedLog->approval_status !== 'processing') {
                return;
            }

            $this->applyRetryState($lockedLog, $message);
        }, 3);
    }

    private function applyRetryState(IsolationLog $log, string $message): void
    {
        $maxAttempts = (int) config('mikrotik.isolation_max_attempts', 5);
        $attempts = (int) $log->attempt_count;
        $hasAttemptsRemaining = $attempts < $maxAttempts;
        $baseMinutes = (int) config('mikrotik.isolation_retry_base_minutes', 2);
        $backoffMinutes = min(60, $baseMinutes * (2 ** max(0, $attempts - 1)));

        $log->update([
            'approval_status' => $hasAttemptsRemaining ? 'approved' : 'failed',
            'next_retry_at' => $hasAttemptsRemaining
                ? now()->addMinutes($backoffMinutes)
                : null,
            'last_error' => $message,
            'notes' => $hasAttemptsRemaining
                ? "Isolir ditunda dan akan dicoba kembali: {$message}"
                : "Isolir gagal setelah {$attempts} percobaan: {$message}",
        ]);
    }

    private function recoverStaleClaims(MikrotikDevice $device): void
    {
        $timeout = (int) config(
            'mikrotik.isolation_processing_timeout_minutes',
            10
        );

        IsolationLog::query()
            ->where('action', 'isolate')
            ->where('execution_mode', 'simulation')
            ->where('approval_status', 'processing')
            ->where('last_attempt_at', '<=', now()->subMinutes($timeout))
            ->whereHas('customer.pppoeAccount', function (Builder $query) use ($device): void {
                $query->where('mikrotik_device_id', $device->id);
            })
            ->update([
                'approval_status' => 'approved',
                'next_retry_at' => now(),
                'last_error' => 'Claim processing kedaluwarsa dan dipulihkan otomatis.',
            ]);
    }
}
