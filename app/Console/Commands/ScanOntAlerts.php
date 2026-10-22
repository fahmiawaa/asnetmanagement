<?php

namespace App\Console\Commands;

use App\Services\OntAlertService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('genieacs:scan-alerts')]
#[Description('Kirim notifikasi ONT offline dan redaman kritis berdasarkan snapshot lokal')]
class ScanOntAlerts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OntAlertService $alertService): int
    {
        try {
            $result = $alertService->scan();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Pemindaian alert ONT gagal.');

            return self::FAILURE;
        }

        $this->info(
            "{$result['offline']} offline/missing, {$result['optical']} redaman bermasalah, ".
            "{$result['notified']} notifikasi terkirim."
        );

        return self::SUCCESS;
    }
}
