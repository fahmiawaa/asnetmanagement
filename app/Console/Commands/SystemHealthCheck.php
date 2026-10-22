<?php

namespace App\Console\Commands;

use App\Services\OperationalAlertService;
use App\Services\SystemHealthService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('asnet:health-check {--no-alert : Jangan kirim alert}')]
#[Description('Memeriksa kesehatan database, antrean, MikroTik, otomasi, disk, dan backup')]
class SystemHealthCheck extends Command
{
    public function handle(
        SystemHealthService $health,
        OperationalAlertService $alerts,
    ): int {
        Cache::put('operations:scheduler:last_run_at', now()->toIso8601String(), now()->addDays(7));
        $report = $health->check(includeScheduler: false);

        foreach ($report['checks'] as $key => $check) {
            $this->line(sprintf(
                '[%s] %s: %s',
                strtoupper($check['status']),
                $check['label'],
                $check['message']
            ));

            if (! $this->option('no-alert') && in_array($check['status'], ['warning', 'critical'], true)) {
                $alerts->send(
                    'health.'.$key,
                    $check['label'].' bermasalah',
                    $check['message'],
                    ['check' => $key, 'value' => $check['value']],
                    $check['status'] === 'critical' ? 'danger' : 'warning',
                );
            }
        }

        return $report['status'] === 'critical' ? self::FAILURE : self::SUCCESS;
    }
}
