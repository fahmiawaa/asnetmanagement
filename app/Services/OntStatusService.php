<?php

namespace App\Services;

use Carbon\CarbonInterface;

class OntStatusService
{
    public function calculate(
        ?CarbonInterface $lastInform,
        ?int $informIntervalSeconds,
        ?CarbonInterface $observedAt = null,
    ): string {
        if ($lastInform === null) {
            return 'unknown';
        }

        $interval = $informIntervalSeconds !== null && $informIntervalSeconds > 0
            ? $informIntervalSeconds
            : (int) config('genieacs.fallback_inform_interval_seconds');
        $age = max(0, (int) $lastInform->diffInSeconds($observedAt ?? now()));

        if ($age <= (2 * $interval) + 60) {
            return 'online';
        }

        if ($age <= (4 * $interval) + 60) {
            return 'warning';
        }

        return 'offline';
    }
}
