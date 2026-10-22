<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use Illuminate\Console\Command;

class SyncAllMikrotikPppoe extends Command
{
    protected $signature = 'mikrotik:sync-all-pppoe';

    protected $description = 'Menyinkronkan akun PPPoE semua perangkat MikroTik';

    public function handle(): int
    {
        $devices = MikrotikDevice::query()
            ->where('connection_status', '!=', 'inactive')
            ->lazyById(25);

        $processedCount = 0;
        $failedCount = 0;

        foreach ($devices as $device) {
            $processedCount++;
            $this->newLine();
            $this->line("Sinkronisasi perangkat: {$device->name} (ID {$device->id})");

            $exitCode = $this->call('mikrotik:sync-pppoe', [
                'deviceId' => $device->id,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $failedCount++;
            }
        }

        if ($processedCount === 0) {
            $this->warn('Belum ada perangkat MikroTik yang terdaftar.');

            return self::SUCCESS;
        }

        if ($failedCount > 0) {
            $this->error("{$failedCount} perangkat gagal disinkronkan.");

            return self::FAILURE;
        }

        $this->info('Semua perangkat MikroTik berhasil disinkronkan.');

        return self::SUCCESS;
    }
}
