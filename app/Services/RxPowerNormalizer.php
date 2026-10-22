<?php

namespace App\Services;

use Carbon\CarbonInterface;

class RxPowerNormalizer
{
    public const SOURCE = 'VirtualParameters.RXPower';

    /**
     * Normalisasi nilai RX power mentah tanpa membuang nilai aslinya.
     */
    public function normalize(
        mixed $raw,
        ?string $ponMode = null,
        ?CarbonInterface $collectedAt = null,
    ): RxPowerReading {
        $rawString = is_scalar($raw) && ! is_bool($raw) ? trim((string) $raw) : null;
        $rawString = $rawString === '' ? null : $rawString;
        $dbm = $this->parseDbm($rawString);

        return new RxPowerReading(
            raw: $rawString,
            dbm: $dbm,
            quality: $dbm === null ? 'unknown' : $this->classify($dbm, $ponMode),
            source: self::SOURCE,
            collectedAt: $collectedAt ?? now(),
        );
    }

    public function classify(float $dbm, ?string $ponMode = null): string
    {
        $thresholds = $this->thresholdsFor($ponMode);

        if ($dbm > $thresholds['overload_above']) {
            return 'overload';
        }

        if ($dbm >= $thresholds['good_min']) {
            return 'good';
        }

        if ($dbm >= $thresholds['warning_min']) {
            return 'warning';
        }

        return 'critical';
    }

    /**
     * @return array{overload_above: float, good_min: float, warning_min: float}
     */
    public function thresholdsFor(?string $ponMode): array
    {
        $configured = (array) config('genieacs.rx_power.thresholds', []);
        $merged = array_merge(
            (array) ($configured['default'] ?? []),
            (array) ($configured[strtolower((string) $ponMode)] ?? []),
        );

        return [
            'overload_above' => (float) ($merged['overload_above'] ?? -8.0),
            'good_min' => (float) ($merged['good_min'] ?? -24.0),
            'warning_min' => (float) ($merged['warning_min'] ?? -27.0),
        ];
    }

    private function parseDbm(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $sentinels = array_map(
            'strtolower',
            (array) config('genieacs.rx_power.sentinels', [])
        );
        $candidate = trim((string) preg_replace('/\s*dbm\s*$/i', '', $raw));

        if (in_array(strtolower($candidate), $sentinels, true)) {
            return null;
        }

        if (! is_numeric($candidate)) {
            return null;
        }

        $value = (float) $candidate;
        $min = (float) config('genieacs.rx_power.min_valid_dbm', -50.0);
        $max = (float) config('genieacs.rx_power.max_valid_dbm', 10.0);

        if (! is_finite($value) || $value < $min || $value > $max) {
            return null;
        }

        return round($value, 2);
    }
}
