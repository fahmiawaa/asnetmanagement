<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use App\Services\MikrotikConnectionService;
use Illuminate\Console\Command;
use Throwable;

class PreviewMikrotikPppoe extends Command
{
    protected $signature = 'mikrotik:preview-pppoe
                            {deviceId : ID perangkat MikroTik}';

    protected $description = 'Menampilkan akun PPPoE MikroTik secara read-only';

    public function handle(
        MikrotikConnectionService $connectionService
    ): int {
        $deviceId = $this->argument('deviceId');

        $device = MikrotikDevice::find($deviceId);

        if (! $device) {
            $this->error(
                "Perangkat MikroTik dengan ID {$deviceId} tidak ditemukan."
            );

            return self::FAILURE;
        }

        try {
            $pppoeSecrets = $connectionService
                ->getPppoeSecrets($device);
        } catch (Throwable $exception) {
            $this->error(
                'Gagal membaca data PPPoE dari MikroTik.'
            );

            $this->line(
                'Detail teknis: '.$exception->getMessage()
            );

            report($exception);

            return self::FAILURE;
        }

        $rows = [];

        foreach ($pppoeSecrets as $pppoe) {
            $rows[] = [
                $pppoe['name'] ?? '-',
                $pppoe['service'] ?? '-',
                $pppoe['profile'] ?? '-',
                $pppoe['disabled'] ?? 'false',
                $pppoe['comment'] ?? '-',
            ];
        }

        $this->info(
            count($rows)
            .' akun PPPoE berhasil dibaca secara read-only.'
        );

        $this->table(
            [
                'Name',
                'Service',
                'Profile',
                'Disabled',
                'Comment',
            ],
            $rows
        );

        return self::SUCCESS;
    }
}
