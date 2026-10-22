<?php

namespace Tests\Feature\Services;

use App\Services\OntStatusService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class OntStatusServiceTest extends TestCase
{
    public function test_calculates_boundaries_from_actual_200_second_interval(): void
    {
        $observedAt = CarbonImmutable::parse('2026-09-30 10:00:00');
        $service = app(OntStatusService::class);

        $this->assertSame(
            'online',
            $service->calculate($observedAt->subSeconds(460), 200, $observedAt)
        );
        $this->assertSame(
            'warning',
            $service->calculate($observedAt->subSeconds(461), 200, $observedAt)
        );
        $this->assertSame(
            'warning',
            $service->calculate($observedAt->subSeconds(860), 200, $observedAt)
        );
        $this->assertSame(
            'offline',
            $service->calculate($observedAt->subSeconds(861), 200, $observedAt)
        );
    }

    public function test_uses_fallback_interval_and_handles_missing_last_inform(): void
    {
        config()->set('genieacs.fallback_inform_interval_seconds', 300);
        $observedAt = CarbonImmutable::parse('2026-09-30 10:00:00');
        $service = app(OntStatusService::class);

        $this->assertSame('unknown', $service->calculate(null, 200, $observedAt));
        $this->assertSame(
            'online',
            $service->calculate($observedAt->subSeconds(660), null, $observedAt)
        );
        $this->assertSame(
            'warning',
            $service->calculate($observedAt->subSeconds(661), null, $observedAt)
        );
    }
}
