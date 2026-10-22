<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\IsolationLog;
use RouterOS\Query;
use Throwable;

class RestoreCustomerService
{
    public function __construct(
        private readonly MikrotikConnectionService $connectionService,
        private readonly AuditLogService $auditLog,
    ) {}

    public function execute(IsolationLog $restoreLog): array
    {
        $customer = $restoreLog->customer()->with('pppoeAccount')->first();
        $pppoeAccount = $customer?->pppoeAccount;
        $device = $pppoeAccount?->mikrotikDevice;

        if (! $customer || ! $pppoeAccount || ! $device) {
            return $this->fail(
                $restoreLog,
                'Restore pending: customer, mapping PPPoE, atau perangkat tidak ditemukan.'
            );
        }

        if ($customer->status !== 'isolated') {
            return $this->fail(
                $restoreLog,
                'Restore dibatalkan: status customer bukan isolated.'
            );
        }

        if ($customer->invoices()->where('status', 'overdue')->exists()) {
            return $this->fail(
                $restoreLog,
                'Restore dibatalkan: pelanggan masih memiliki invoice overdue.'
            );
        }

        if (! config('mikrotik.allow_real_actions')) {
            return $this->fail(
                $restoreLog,
                'Restore pending: real action masih dikunci oleh kill switch.'
            );
        }

        try {
            $liveAccounts = $this->connectionService->getPppoeSecrets($device);
            $liveAccount = collect($liveAccounts)
                ->firstWhere('name', $pppoeAccount->username);

            if (! $liveAccount || empty($liveAccount['.id'])) {
                return $this->fail(
                    $restoreLog,
                    "Restore pending: akun {$pppoeAccount->username} tidak ditemukan di MikroTik."
                );
            }

            $client = $this->connectionService->connect($device);
            $client
                ->query((new Query('/ppp/secret/enable'))
                    ->equal('.id', $liveAccount['.id']))
                ->read();

            $verifiedAccounts = $this->connectionService->getPppoeSecrets($device);
            $verifiedAccount = collect($verifiedAccounts)
                ->firstWhere('name', $pppoeAccount->username);
            $disabledValue = strtolower((string) ($verifiedAccount['disabled'] ?? 'true'));

            if (in_array($disabledValue, ['true', 'yes', '1'], true)) {
                return $this->fail(
                    $restoreLog,
                    "Restore pending: MikroTik belum mengonfirmasi {$pppoeAccount->username} sebagai enabled."
                );
            }

            $pppoeAccount->update([
                'disabled' => false,
                'is_present' => true,
                'last_synced_at' => now(),
            ]);

            $customer->update(['status' => 'active']);

            $message = 'Pembayaran terverifikasi dan layanan berhasil dipulihkan.';
            $restoreLog->update([
                'approval_status' => 'executed',
                'execution_mode' => 'real',
                'executed_at' => now(),
                'next_retry_at' => null,
                'last_error' => null,
                'notes' => $message,
            ]);

            $this->auditLog->record(
                'restore.executed',
                $restoreLog,
                'Layanan pelanggan berhasil dipulihkan dari proses restore.'
            );

            return [
                'success' => true,
                'message' => $message,
                'whatsapp_url' => $this->whatsappUrl($customer),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail(
                $restoreLog,
                'Restore pending: koneksi atau verifikasi MikroTik gagal. Admin perlu memeriksa dan sistem akan mencoba lagi.'
            );
        }
    }

    private function fail(IsolationLog $restoreLog, string $message): array
    {
        $restoreLog->update([
            'approval_status' => 'pending',
            'execution_mode' => 'simulation',
            'notes' => $message,
        ]);

        $this->auditLog->record(
            'restore.failed',
            $restoreLog,
            $message
        );

        return [
            'success' => false,
            'message' => $message,
            'whatsapp_url' => null,
        ];
    }

    private function whatsappUrl(Customer $customer): ?string
    {
        $number = preg_replace('/\D+/', '', (string) $customer->whatsapp_number);

        if (! $number) {
            return null;
        }

        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode(
            'Layanan internet '.$customer->name.' sudah berhasil dipulihkan.'
        );
    }
}
