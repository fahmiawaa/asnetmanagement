<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use App\Services\MikrotikSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncMikrotikPppoe extends Command
{
    protected $signature = 'mikrotik:sync-pppoe
                            {deviceId : ID perangkat MikroTik}';

    protected $description =
        'Menyinkronkan PPP Profile, PPP Secret, dan sesi aktif MikroTik';

    public function handle(MikrotikSyncService $syncService): int
    {
        $deviceId = $this->argument('deviceId');
        $device = MikrotikDevice::find($deviceId);

        if (! $device) {
            $this->error(
                "Perangkat MikroTik dengan ID {$deviceId} tidak ditemukan."
            );

            return self::FAILURE;
        }

        try {
            $result = $syncService->sync($device);
        } catch (Throwable $exception) {
            $device->update(['connection_status' => 'failed']);
            $this->error('Sinkronisasi MikroTik gagal dijalankan.');
            $this->line('Detail teknis: '.$exception->getMessage());
            report($exception);

            return self::FAILURE;
        }

        $this->info('Sinkronisasi MikroTik berhasil.');
        $this->table(
            ['Data', 'Baru', 'Diperbarui', 'Hilang/Nonaktif', 'Dilewati'],
            [
                [
                    'PPP Profile',
                    $result['profiles']['created'],
                    $result['profiles']['updated'],
                    $result['profiles']['missing'],
                    $result['profiles']['skipped'],
                ],
                [
                    'PPP Secret',
                    $result['secrets']['created'],
                    $result['secrets']['updated'],
                    $result['secrets']['missing'],
                    $result['secrets']['skipped'],
                ],
                [
                    'Sesi Aktif',
                    $result['sessions']['created'],
                    $result['sessions']['updated'],
                    $result['sessions']['inactive'],
                    $result['sessions']['skipped'],
                ],
            ]
        );

        return self::SUCCESS;
    }
}
