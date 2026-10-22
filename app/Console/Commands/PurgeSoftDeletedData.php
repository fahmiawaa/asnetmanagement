<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\MikrotikDevice;
use Illuminate\Console\Command;

class PurgeSoftDeletedData extends Command
{
    protected $signature = 'data:purge-trashed
                            {--confirm= : Wajib PURGE-TRASHED}';

    protected $description = 'Menghapus permanen semua data soft-delete setelah konfirmasi eksplisit';

    public function handle(): int
    {
        if ($this->option('confirm') !== 'PURGE-TRASHED') {
            $this->error('Gunakan --confirm=PURGE-TRASHED untuk menghapus permanen data soft-delete.');

            return self::FAILURE;
        }

        $models = [
            'customers' => Customer::onlyTrashed(),
            'internet packages' => InternetPackage::onlyTrashed(),
            'MikroTik devices' => MikrotikDevice::onlyTrashed(),
        ];

        foreach ($models as $label => $query) {
            $count = 0;
            $query->chunkById(100, function ($records) use (&$count) {
                foreach ($records as $record) {
                    $record->forceDelete();
                    $count++;
                }
            });

            $this->line("{$label}: {$count} data dihapus permanen.");
        }

        $this->warn('Purge selesai. Data yang dihapus permanen tidak dapat dipulihkan.');

        return self::SUCCESS;
    }
}
