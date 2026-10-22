<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use Illuminate\Console\Command;

class AutoIsolateCustomers extends Command
{
    protected $signature = 'billing:auto-isolate';

    protected $description = 'Membuat kandidat dan menjalankan isolasi otomatis dengan kill switch';

    public function handle(): int
    {
        $this->call('isolation:simulate');

        if (! config('mikrotik.auto_isolation_enabled')) {
            $this->warn('Auto-isolasi belum diaktifkan. Kandidat tetap dicatat untuk review.');

            return self::SUCCESS;
        }

        if (! config('mikrotik.allow_real_actions')) {
            $this->warn('Auto-isolasi aktif, tetapi real action masih dikunci oleh kill switch.');

            return self::SUCCESS;
        }

        $failed = 0;
        $limit = (int) config('mikrotik.isolation_batch_size', 25);

        foreach (
            MikrotikDevice::query()
                ->where('connection_status', '!=', 'inactive')
                ->lazyById(25) as $device
        ) {
            $exitCode = $this->call('billing:execute-approved', [
                'deviceId' => $device->id,
                '--execute' => true,
                '--confirm' => 'ISOLATE',
                '--limit' => $limit,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $failed++;
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
