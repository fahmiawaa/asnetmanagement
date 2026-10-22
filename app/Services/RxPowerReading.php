<?php

namespace App\Services;

use Carbon\CarbonInterface;

final readonly class RxPowerReading
{
    public function __construct(
        public ?string $raw,
        public ?float $dbm,
        public string $quality,
        public string $source,
        public CarbonInterface $collectedAt,
    ) {}
}
