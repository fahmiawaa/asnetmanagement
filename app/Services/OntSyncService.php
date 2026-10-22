<?php

namespace App\Services;

use App\Models\GenieacsServer;
use App\Models\OntDevice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class OntSyncService
{
    public function __construct(
        private readonly GenieAcsService $genieAcs,
        private readonly OntStatusService $statusService,
        private readonly RxPowerNormalizer $rxNormalizer,
    ) {}

    /**
     * @return array{processed: int, created: int, updated: int, missing: int}
     */
    public function sync(GenieacsServer $server): array
    {
        $startedAt = now();
        $limit = max(1, (int) config('genieacs.sync_chunk_size'));
        $skip = 0;
        $result = ['processed' => 0, 'created' => 0, 'updated' => 0, 'missing' => 0];

        do {
            $devices = $this->genieAcs->fetchDevices($server, limit: $limit, skip: $skip);

            DB::transaction(function () use ($devices, $server, $startedAt, &$result): void {
                foreach ($devices as $payload) {
                    $this->syncDevice($server, $payload, $startedAt, $result);
                }
            });

            $count = count($devices);
            $result['processed'] += $count;
            $skip += $count;
        } while ($count === $limit);

        $result['missing'] = $this->markMissingDevices($server, $startedAt);
        $server->forceFill([
            'connection_status' => 'connected',
            'last_connected_at' => now(),
            'last_sync_at' => now(),
            'last_error_at' => null,
            'last_error_message' => null,
        ])->save();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{processed: int, created: int, updated: int, missing: int}  $result
     */
    private function syncDevice(
        GenieacsServer $server,
        array $payload,
        CarbonInterface $observedAt,
        array &$result,
    ): void {
        $deviceId = $this->scalarString(data_get($payload, '_id'));

        if ($deviceId === null) {
            return;
        }

        $device = OntDevice::query()->firstOrNew([
            'genieacs_device_id' => $deviceId,
        ]);
        $wasRecentlyCreated = ! $device->exists;
        $oldStatus = $device->exists ? $device->connection_status : null;
        $lastInform = $this->parseDate(data_get($payload, '_lastInform'));
        $interval = $this->positiveInteger(
            $this->parameterValue(
                $payload,
                'InternetGatewayDevice.ManagementServer.PeriodicInformInterval'
            ) ?? $this->parameterValue(
                $payload,
                'Device.ManagementServer.PeriodicInformInterval'
            )
        );
        $status = $this->statusService->calculate($lastInform, $interval, $observedAt);
        $ponModeRaw = $this->scalarString(
            $this->parameterValue($payload, 'VirtualParameters.getponmode')
        );
        $ponMode = $this->normalizePonMode($ponModeRaw);
        $rxPower = $this->rxNormalizer->normalize(
            $this->parameterValue($payload, 'VirtualParameters.RXPower'),
            $ponMode,
            $observedAt,
        );
        $temperatureRaw = $this->scalarString(
            $this->parameterValue($payload, 'VirtualParameters.gettemp')
        );

        $device->fill([
            'genieacs_server_id' => $server->id,
            'serial_number' => $this->firstString(
                $this->parameterValue($payload, 'VirtualParameters.getSerialNumber'),
                data_get($payload, '_deviceId._SerialNumber')
            ),
            'pon_mac' => $this->scalarString(
                $this->parameterValue($payload, 'VirtualParameters.PonMac')
            ),
            'manufacturer' => $this->scalarString(data_get($payload, '_deviceId._Manufacturer')),
            'oui' => $this->scalarString(data_get($payload, '_deviceId._OUI')),
            'product_class' => $this->scalarString(data_get($payload, '_deviceId._ProductClass')),
            'pon_mode' => $ponMode,
            'ip_address' => $this->validIp(
                $this->parameterValue($payload, 'VirtualParameters.IPTR069')
            ),
            'pppoe_ip' => $this->validIp(
                $this->parameterValue($payload, 'VirtualParameters.pppoeIP')
            ),
            'pppoe_mac' => $this->scalarString(
                $this->parameterValue($payload, 'VirtualParameters.pppoeMac')
            ),
            'inform_interval_seconds' => $interval,
            'last_inform_at' => $lastInform,
            'connection_status' => $status,
            'rx_power_raw' => $rxPower->raw,
            'rx_power_dbm' => $rxPower->dbm,
            'rx_power_quality' => $rxPower->quality,
            'rx_power_source' => $rxPower->source,
            'rx_power_collected_at' => $rxPower->collectedAt,
            'temperature_raw' => $temperatureRaw,
            'temperature_celsius' => $this->validTemperature($temperatureRaw),
            'active_wifi_devices' => $this->nonNegativeInteger(
                $this->parameterValue($payload, 'VirtualParameters.activedevices')
            ),
            'device_uptime_seconds' => $this->nonNegativeInteger(
                $this->parameterValue($payload, 'VirtualParameters.getdeviceuptime')
            ),
            'pppoe_uptime_seconds' => $this->nonNegativeInteger(
                $this->parameterValue($payload, 'VirtualParameters.getpppuptime')
            ),
            'last_seen_at' => $observedAt,
            'last_synced_at' => $observedAt,
            'missing_since' => null,
        ]);
        $device->save();

        $result[$wasRecentlyCreated ? 'created' : 'updated']++;

        if ($oldStatus !== $status) {
            $device->statusHistories()->create([
                'old_status' => $oldStatus,
                'new_status' => $status,
                'last_inform_at' => $lastInform,
                'rx_power_dbm' => $rxPower->dbm,
                'observed_at' => $observedAt,
                'source' => 'genieacs_sync',
            ]);
        }
    }

    private function markMissingDevices(
        GenieacsServer $server,
        CarbonInterface $syncStartedAt,
    ): int {
        $missingAfter = now()->subMinutes(
            max(1, (int) config('genieacs.missing_after_minutes'))
        );
        $missing = 0;

        OntDevice::query()
            ->where('genieacs_server_id', $server->id)
            ->where(function ($query) use ($syncStartedAt): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $syncStartedAt);
            })
            ->lazyById(100)
            ->each(function (OntDevice $device) use ($missingAfter, &$missing): void {
                if ($device->missing_since === null) {
                    $device->missing_since = now();
                    $device->save();

                    return;
                }

                if ($device->missing_since->isAfter($missingAfter)) {
                    return;
                }

                if ($device->connection_status !== 'missing') {
                    $oldStatus = $device->connection_status;
                    $device->connection_status = 'missing';
                    $device->save();
                    $device->statusHistories()->create([
                        'old_status' => $oldStatus,
                        'new_status' => 'missing',
                        'last_inform_at' => $device->last_inform_at,
                        'rx_power_dbm' => $device->rx_power_dbm,
                        'observed_at' => now(),
                        'source' => 'genieacs_sync',
                    ]);
                }

                $missing++;
            });

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parameterValue(array $payload, string $path): mixed
    {
        $value = data_get($payload, $path);

        if (is_array($value) && array_key_exists('_value', $value)) {
            return $value['_value'];
        }

        return $value;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value < 0) {
            return null;
        }

        return (int) $value;
    }

    private function validTemperature(?string $raw): ?float
    {
        if ($raw === null || ! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        return $value >= -40 && $value <= 150 ? round($value, 2) : null;
    }

    private function validIp(mixed $value): ?string
    {
        $ip = $this->scalarString($value);

        return $ip !== null && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private function scalarString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' && strtolower($string) !== 'n/a' ? $string : null;
    }

    private function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (($string = $this->scalarString($value)) !== null) {
                return $string;
            }
        }

        return null;
    }

    private function normalizePonMode(?string $value): string
    {
        $normalized = strtolower((string) $value);

        if (str_contains($normalized, 'gpon')) {
            return 'gpon';
        }

        if (str_contains($normalized, 'epon')) {
            return 'epon';
        }

        return 'unknown';
    }
}
