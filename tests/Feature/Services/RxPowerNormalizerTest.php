<?php

namespace Tests\Feature\Services;

use App\Services\RxPowerNormalizer;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class RxPowerNormalizerTest extends TestCase
{
    public function test_classifies_default_threshold_boundaries(): void
    {
        $normalizer = app(RxPowerNormalizer::class);

        $this->assertSame('overload', $normalizer->normalize('-7.99')->quality);
        $this->assertSame('good', $normalizer->normalize('-8')->quality);
        $this->assertSame('good', $normalizer->normalize('-24')->quality);
        $this->assertSame('warning', $normalizer->normalize('-24.01')->quality);
        $this->assertSame('warning', $normalizer->normalize('-27')->quality);
        $this->assertSame('critical', $normalizer->normalize('-27.01')->quality);
    }

    public function test_keeps_raw_value_and_records_source_and_collection_time(): void
    {
        $collectedAt = CarbonImmutable::parse('2026-09-30 10:00:00');

        $reading = app(RxPowerNormalizer::class)->normalize(' -20.5 dBm ', 'gpon', $collectedAt);

        $this->assertSame('-20.5 dBm', $reading->raw);
        $this->assertSame(-20.5, $reading->dbm);
        $this->assertSame('good', $reading->quality);
        $this->assertSame('VirtualParameters.RXPower', $reading->source);
        $this->assertTrue($reading->collectedAt->equalTo($collectedAt));
    }

    public function test_treats_empty_sentinel_and_non_numeric_values_as_unknown(): void
    {
        $normalizer = app(RxPowerNormalizer::class);

        foreach ([null, '', '   ', 'N/A', 'n/a', '-inf', 'abc', ['x'], new \stdClass, true, false] as $raw) {
            $reading = $normalizer->normalize($raw);

            $this->assertNull($reading->dbm);
            $this->assertSame('unknown', $reading->quality);
        }

        $this->assertSame('N/A', $normalizer->normalize('N/A')->raw);
        $this->assertNull($normalizer->normalize('')->raw);
    }

    public function test_rejects_values_outside_valid_range(): void
    {
        $normalizer = app(RxPowerNormalizer::class);

        foreach (['-50.01', '10.01', '65535', '-65535', '-9999', '2147483647'] as $raw) {
            $reading = $normalizer->normalize($raw);

            $this->assertNull($reading->dbm, "Nilai {$raw} seharusnya tidak valid.");
            $this->assertSame('unknown', $reading->quality);
        }

        $this->assertSame('critical', $normalizer->normalize('-50')->quality);
        $this->assertSame('overload', $normalizer->normalize('10')->quality);
    }

    public function test_thresholds_can_be_overridden_per_technology(): void
    {
        config()->set('genieacs.rx_power.thresholds.epon', [
            'good_min' => -26.0,
            'warning_min' => -28.0,
        ]);
        $normalizer = app(RxPowerNormalizer::class);

        $this->assertSame('good', $normalizer->normalize('-25', 'epon')->quality);
        $this->assertSame('warning', $normalizer->normalize('-25', 'gpon')->quality);
        $this->assertSame('warning', $normalizer->normalize('-28', 'epon')->quality);
        $this->assertSame('critical', $normalizer->normalize('-28.01', 'epon')->quality);
    }
}
