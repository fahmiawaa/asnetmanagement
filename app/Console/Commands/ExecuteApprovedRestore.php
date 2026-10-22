<?php

namespace App\Console\Commands;

use App\Models\IsolationLog;
use App\Models\MikrotikDevice;
use App\Services\MikrotikConnectionService;
use Illuminate\Console\Command;
use RouterOS\Query;
use Throwable;

class ExecuteApprovedRestore extends Command
{
    protected $signature = 'billing:execute-approved-restore
                            {deviceId : ID perangkat MikroTik}
                            {--execute : Jalankan tindakan nyata}
                            {--confirm= : Konfirmasi wajib RESTORE}';

    protected $description = 'Memproses restore approved dengan mode simulasi sebagai default';

    public function handle(
        MikrotikConnectionService $connectionService
    ): int {
        $deviceId = $this->argument('deviceId');
        $executeRealAction = $this->option('execute');

        if (
            $executeRealAction
            && ! config('mikrotik.allow_real_actions')
        ) {
            $this->error(
                'Tindakan nyata sedang dikunci oleh konfigurasi aplikasi.'
            );

            $this->line(
                'Set MIKROTIK_ALLOW_REAL_ACTIONS=true '
                .'hanya saat tindakan benar-benar disetujui.'
            );

            return self::FAILURE;
        }

        $confirmation = $this->option('confirm');

        if ($executeRealAction && $confirmation !== 'RESTORE') {
            $this->error(
                'Tindakan nyata membutuhkan --confirm=RESTORE.'
            );

            return self::FAILURE;
        }

        $device = MikrotikDevice::find($deviceId);

        if (! $device) {
            $this->error(
                "Perangkat MikroTik dengan ID {$deviceId} tidak ditemukan."
            );

            return self::FAILURE;
        }

        $approvedLogs = IsolationLog::with([
            'customer.pppoeAccount',
        ])
            ->where('action', 'restore')
            ->where('execution_mode', 'simulation')
            ->where('approval_status', 'approved')
            ->whereHas('customer.pppoeAccount', function ($query) use ($deviceId) {
                $query->where('mikrotik_device_id', $deviceId);
            })
            ->get();

        if ($approvedLogs->isEmpty()) {
            $this->info(
                'Tidak ada kandidat restore approved yang siap diproses.'
            );

            return self::SUCCESS;
        }

        try {
            $liveAccounts = $connectionService
                ->getPppoeSecrets($device);
        } catch (Throwable $exception) {
            $this->error(
                'Gagal membaca akun PPPoE dari MikroTik.'
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

        $rows = [];
        $readyCount = 0;
        $blockedCount = 0;
        $failedCount = 0;

        foreach ($approvedLogs as $log) {
            $customer = $log->customer;
            $pppoeAccount = $customer?->pppoeAccount;

            if (! $pppoeAccount) {
                $blockedCount++;

                $rows[] = [
                    $customer->customer_code ?? '-',
                    '-',
                    'BLOCKED - Belum mapping PPPoE',
                ];

                continue;
            }

            $overdueExists = $customer
                ->invoices()
                ->where('status', 'overdue')
                ->exists();

            if ($overdueExists) {
                $blockedCount++;

                $rows[] = [
                    $customer->customer_code,
                    $pppoeAccount->username,
                    'BLOCKED - Masih memiliki invoice overdue',
                ];

                continue;
            }

            $username = $pppoeAccount->username;
            $liveAccount = $liveAccountsByUsername->get($username);

            if (! $liveAccount) {
                $blockedCount++;

                $rows[] = [
                    $customer->customer_code,
                    $username,
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

            if (! $isDisabled) {
                $blockedCount++;

                $rows[] = [
                    $customer->customer_code,
                    $username,
                    'SKIP - Akun sudah active',
                ];

                continue;
            }

            $readyCount++;

            if (! $executeRealAction) {
                $rows[] = [
                    $customer->customer_code,
                    $username,
                    'SIMULATION - Akan direstore',
                ];

                continue;
            }

            if (empty($liveAccount['.id'])) {
                $blockedCount++;

                $rows[] = [
                    $customer->customer_code,
                    $username,
                    'BLOCKED - ID akun MikroTik tidak tersedia',
                ];

                continue;
            }

            try {
                $client = $connectionService->connect($device);

                $query = (new Query('/ppp/secret/enable'))
                    ->equal('.id', $liveAccount['.id']);

                $client
                    ->query($query)
                    ->read();

                /*
                |--------------------------------------------------------------------------
                | Verifikasi bahwa secret benar-benar enabled
                |--------------------------------------------------------------------------
                */
                $verificationAccounts = $connectionService
                    ->getPppoeSecrets($device);

                $verifiedAccount = collect($verificationAccounts)
                    ->firstWhere('name', $username);

                $verifiedDisabledValue = strtolower(
                    (string) ($verifiedAccount['disabled'] ?? 'true')
                );

                $verifiedDisabled = in_array(
                    $verifiedDisabledValue,
                    ['true', 'yes', '1'],
                    true
                );

                if ($verifiedDisabled) {
                    throw new \RuntimeException(
                        "RouterOS belum mengonfirmasi akun {$username} "
                        .'sebagai enabled.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Update database hanya setelah verifikasi berhasil
                |--------------------------------------------------------------------------
                */
                $pppoeAccount->update([
                    'disabled' => false,
                    'is_present' => true,
                    'last_synced_at' => now(),
                ]);

                $customer->update([
                    'status' => 'active',
                ]);

                $log->update([
                    'approval_status' => 'executed',
                    'execution_mode' => 'real',
                    'executed_at' => now(),
                    'notes' => 'Secret PPPoE berhasil diaktifkan dan diverifikasi di MikroTik.',
                ]);

                $rows[] = [
                    $customer->customer_code,
                    $username,
                    'EXECUTED - Akun berhasil enabled',
                ];
            } catch (Throwable $exception) {
                $blockedCount++;
                $failedCount++;

                $rows[] = [
                    $customer->customer_code,
                    $username,
                    'FAILED - Tindakan tidak berhasil',
                ];

                report($exception);
            }
        }

        $this->table(
            [
                'Kode Customer',
                'Username PPPoE',
                'Hasil',
            ],
            $rows
        );

        $this->newLine();

        if (! $executeRealAction) {
            $this->warn(
                'Mode simulasi aktif. Tidak ada perubahan pada MikroTik atau database.'
            );
        } else {
            $this->warn(
                'Mode eksekusi nyata telah digunakan. Periksa hasil dan log segera.'
            );
        }

        $this->line("Siap diproses : {$readyCount}");
        $this->line("Diblokir      : {$blockedCount}");

        return $executeRealAction && $failedCount > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
