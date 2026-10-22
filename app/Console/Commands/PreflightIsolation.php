<?php

namespace App\Console\Commands;

use App\Models\IsolationLog;
use App\Models\MikrotikDevice;
use App\Services\MikrotikConnectionService;
use Illuminate\Console\Command;
use Throwable;

class PreflightIsolation extends Command
{
    protected $signature = 'billing:preflight-isolation
                            {deviceId : ID perangkat MikroTik}
                            {--limit= : Maksimum kandidat yang diperiksa}';

    protected $description = 'Memeriksa kandidat isolir approved tanpa mengubah MikroTik';

    public function handle(
        MikrotikConnectionService $connectionService
    ): int {
        $deviceId = $this->argument('deviceId');
        $configuredLimit = (int) config('mikrotik.isolation_batch_size', 25);
        $limit = max(
            1,
            min(100, (int) ($this->option('limit') ?: $configuredLimit))
        );

        $device = MikrotikDevice::find($deviceId);

        if (! $device) {
            $this->error(
                "Perangkat MikroTik dengan ID {$deviceId} tidak ditemukan."
            );

            return self::FAILURE;
        }

        try {
            $liveAccounts = $connectionService
                ->getPppoeSecrets($device);
        } catch (Throwable $exception) {
            $this->error(
                'Preflight gagal membaca data PPPoE dari MikroTik.'
            );

            $this->line(
                'Detail teknis: '.$exception->getMessage()
            );

            report($exception);

            return self::FAILURE;
        }

        $liveAccountsByUsername = collect($liveAccounts)
            ->filter(function ($account) {
                return ! empty($account['name']);
            })
            ->keyBy('name');

        $approvedLogs = IsolationLog::with([
            'customer.pppoeAccount',
        ])
            ->where('action', 'isolate')
            ->where('execution_mode', 'simulation')
            ->where('approval_status', 'approved')
            ->whereHas('customer.pppoeAccount', function ($query) use ($deviceId) {
                $query->where('mikrotik_device_id', $deviceId);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($approvedLogs->isEmpty()) {
            $this->info(
                'Tidak ada kandidat isolir approved untuk perangkat ini.'
            );

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($approvedLogs as $log) {
            $customer = $log->customer;
            $pppoeAccount = $customer?->pppoeAccount;

            if (! $pppoeAccount) {
                $rows[] = [
                    $customer->customer_code ?? '-',
                    $customer->name ?? '-',
                    '-',
                    'Tidak',
                    '-',
                    'BLOCKED - Belum mapping PPPoE',
                ];

                continue;
            }

            $username = $pppoeAccount->username;
            $liveAccount = $liveAccountsByUsername->get($username);

            if (! $liveAccount) {
                $rows[] = [
                    $customer->customer_code,
                    $customer->name,
                    $username,
                    'Tidak',
                    '-',
                    'BLOCKED - Akun tidak ditemukan',
                ];

                continue;
            }

            $disabledValue = strtolower(
                (string) ($liveAccount['disabled'] ?? 'false')
            );

            $isDisabled = in_array(
                $disabledValue,
                ['true', 'yes', '1'],
                true
            );

            $rows[] = [
                $customer->customer_code,
                $customer->name,
                $username,
                'Ya',
                $isDisabled ? 'Disabled' : 'Active',
                $isDisabled
                    ? 'SKIP - Sudah disabled'
                    : 'READY - Menunggu eksekusi',
            ];
        }

        $this->table(
            [
                'Kode',
                'Customer',
                'Username PPPoE',
                'Ditemukan',
                'Status MikroTik',
                'Hasil Preflight',
            ],
            $rows
        );

        $this->newLine();

        $this->info(
            "Preflight selesai untuk maksimal {$limit} kandidat. Tidak ada perubahan pada MikroTik."
        );

        return self::SUCCESS;
    }
}
