<?php

namespace Tests\Feature\Services;

use App\Models\GenieacsServer;
use App\Models\OntDevice;
use App\Services\OntSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OntSyncServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_synchronizes_multi_vendor_devices_into_safe_local_snapshot(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        config()->set('genieacs.sync_chunk_size', 2);
        Http::preventStrayRequests();
        Http::fakeSequence('http://genieacs.test:7557/devices*')
            ->push([
                $this->devicePayload(
                    'ZTE-1',
                    'ZTE',
                    'F660',
                    'ZTESERIAL01',
                    'GPON',
                    now()->subSeconds(100)->toIso8601String(),
                    '-20.50'
                ),
                $this->devicePayload(
                    'FH-1',
                    'FiberHome',
                    'AN5506',
                    'FHSERIAL01',
                    'EPON',
                    now()->subSeconds(900)->toIso8601String(),
                    'N/A'
                ),
            ])
            ->push([]);
        $server = GenieacsServer::factory()->create([
            'base_url' => 'http://genieacs.test:7557',
        ]);

        $result = app(OntSyncService::class)->sync($server);

        $this->assertSame([
            'processed' => 2,
            'created' => 2,
            'updated' => 0,
            'missing' => 0,
        ], $result);
        $this->assertDatabaseHas('ont_devices', [
            'genieacs_device_id' => 'ZTE-1',
            'manufacturer' => 'ZTE',
            'serial_number' => 'ZTESERIAL01',
            'pon_mode' => 'gpon',
            'connection_status' => 'online',
            'rx_power_raw' => '-20.50',
            'rx_power_quality' => 'good',
            'rx_power_source' => 'VirtualParameters.RXPower',
        ]);
        $zte = OntDevice::query()->where('genieacs_device_id', 'ZTE-1')->firstOrFail();
        $this->assertSame('-20.50', (string) $zte->rx_power_dbm);
        $this->assertSame('45.50', (string) $zte->temperature_celsius);
        $this->assertNotNull($zte->rx_power_collected_at);
        $this->assertSame(
            '-20.50',
            (string) $zte->statusHistories()->firstOrFail()->rx_power_dbm
        );
        $this->assertDatabaseHas('ont_devices', [
            'genieacs_device_id' => 'FH-1',
            'pon_mode' => 'epon',
            'connection_status' => 'offline',
            'rx_power_raw' => 'N/A',
            'rx_power_dbm' => null,
            'rx_power_quality' => 'unknown',
        ]);
        $this->assertDatabaseCount('ont_status_histories', 2);
        $this->assertSame('connected', $server->fresh()->connection_status);
        $this->assertNotNull($server->fresh()->last_sync_at);
    }

    public function test_marks_device_missing_only_after_grace_period(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        config()->set('genieacs.missing_after_minutes', 30);
        Http::preventStrayRequests();
        Http::fake([
            'http://genieacs.test:7557/devices*' => Http::response([]),
        ]);
        $server = GenieacsServer::factory()->create([
            'base_url' => 'http://genieacs.test:7557',
        ]);
        $device = OntDevice::factory()->create([
            'genieacs_server_id' => $server->id,
            'connection_status' => 'offline',
            'last_seen_at' => now()->subHour(),
            'missing_since' => now()->subMinutes(31),
        ]);

        $result = app(OntSyncService::class)->sync($server);

        $this->assertSame(1, $result['missing']);
        $this->assertSame('missing', $device->fresh()->connection_status);
        $this->assertDatabaseHas('ont_status_histories', [
            'ont_device_id' => $device->id,
            'old_status' => 'offline',
            'new_status' => 'missing',
        ]);
    }

    public function test_syncs_three_hundred_devices_in_nbi_batches_of_one_hundred(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        config()->set('genieacs.sync_chunk_size', 100);
        Http::preventStrayRequests();

        $sequence = Http::fakeSequence('http://genieacs.test:7557/devices*');

        for ($page = 0; $page < 3; $page++) {
            $devices = [];

            for ($offset = 1; $offset <= 100; $offset++) {
                $number = $page * 100 + $offset;

                $devices[] = $this->devicePayload(
                    sprintf('BATCH-%03d', $number),
                    'ZTE',
                    'F660',
                    sprintf('BATCHSERIAL%05d', $number),
                    'GPON',
                    now()->subSeconds(30)->toIso8601String(),
                    '-20.50'
                );
            }

            $sequence->push($devices);
        }

        $sequence->push([]);

        $server = GenieacsServer::factory()->create([
            'base_url' => 'http://genieacs.test:7557',
        ]);

        $result = app(OntSyncService::class)->sync($server);

        $this->assertSame([
            'processed' => 300,
            'created' => 300,
            'updated' => 0,
            'missing' => 0,
        ], $result);
        $this->assertSame(300, OntDevice::query()->count());
        Http::assertSentCount(4);

        $skips = Http::recorded()->map(function (array $pair): int {
            parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

            return (int) ($query['skip'] ?? -1);
        })->all();

        $this->assertSame([0, 100, 200, 300], $skips);
    }

    /**
     * @return array<string, mixed>
     */
    private function devicePayload(
        string $id,
        string $manufacturer,
        string $productClass,
        string $serial,
        string $ponMode,
        string $lastInform,
        string $rxPower,
    ): array {
        return [
            '_id' => $id,
            '_lastInform' => $lastInform,
            '_deviceId' => [
                '_Manufacturer' => $manufacturer,
                '_OUI' => 'A1B2C3',
                '_ProductClass' => $productClass,
                '_SerialNumber' => $serial,
            ],
            'VirtualParameters' => [
                'IPTR069' => ['_value' => '192.168.100.1'],
                'PonMac' => ['_value' => 'AA:BB:CC:DD:EE:FF'],
                'RXPower' => ['_value' => $rxPower],
                'getponmode' => ['_value' => $ponMode],
                'activedevices' => ['_value' => 3],
                'gettemp' => ['_value' => '45.5'],
            ],
            'InternetGatewayDevice' => [
                'ManagementServer' => [
                    'PeriodicInformInterval' => ['_value' => 200],
                ],
            ],
        ];
    }
}
