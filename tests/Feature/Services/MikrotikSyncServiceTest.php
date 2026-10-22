<?php

namespace Tests\Feature\Services;

use App\Models\MikrotikDevice;
use App\Models\PppoeAccount;
use App\Models\PppoeActiveSession;
use App\Services\MikrotikConnectionService;
use App\Services\MikrotikSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MikrotikSyncServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_synchronizes_700_accounts_and_marks_missing_records_without_row_by_row_writes(): void
    {
        $device = MikrotikDevice::query()->create([
            'name' => 'Router Desa A',
            'host' => '10.10.10.1',
            'api_port' => 8728,
            'use_ssl' => false,
            'username' => 'billing-read-write',
            'encrypted_password' => 'encrypted-test-value',
        ]);
        $firstSecrets = $this->routerSecrets(700);
        $secondSecrets = $this->routerSecrets(699, 'profile-baru');
        $firstSessions = $this->routerSessions(700);
        $secondSessions = $this->routerSessions(699);
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldReceive('getPppProfiles')
            ->twice()
            ->andReturn([], []);
        $connectionService->shouldReceive('getPppoeSecrets')
            ->twice()
            ->andReturn($firstSecrets, $secondSecrets);
        $connectionService->shouldReceive('getActivePppoeSessions')
            ->twice()
            ->andReturn($firstSessions, $secondSessions);
        $syncService = new MikrotikSyncService($connectionService);

        $firstResult = $syncService->sync($device);
        $secondResult = $syncService->sync($device);

        $this->assertSame(700, $firstResult['secrets']['created']);
        $this->assertSame(0, $firstResult['secrets']['updated']);
        $this->assertSame(0, $firstResult['secrets']['missing']);
        $this->assertSame(0, $secondResult['secrets']['created']);
        $this->assertSame(699, $secondResult['secrets']['updated']);
        $this->assertSame(1, $secondResult['secrets']['missing']);
        $this->assertSame(
            699,
            PppoeAccount::query()
                ->where('mikrotik_device_id', $device->id)
                ->where('is_present', true)
                ->count()
        );
        $this->assertSame(
            1,
            PppoeAccount::query()
                ->where('mikrotik_device_id', $device->id)
                ->where('is_present', false)
                ->count()
        );
        $this->assertSame(
            699,
            PppoeActiveSession::query()
                ->where('mikrotik_device_id', $device->id)
                ->where('is_active', true)
                ->count()
        );
        $this->assertSame(
            1,
            PppoeActiveSession::query()
                ->where('mikrotik_device_id', $device->id)
                ->where('is_active', false)
                ->count()
        );
        $this->assertSame(
            'profile-baru',
            PppoeAccount::query()
                ->where('mikrotik_device_id', $device->id)
                ->where('username', 'pelanggan-0001')
                ->value('profile')
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function routerSecrets(int $count, string $profile = 'profile-20m'): array
    {
        return collect(range(1, $count))
            ->map(fn (int $number): array => [
                'name' => 'pelanggan-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'service' => 'pppoe',
                'profile' => $profile,
                'disabled' => 'false',
                'remote-address' => '10.20.'.intdiv($number, 254).'.'.(($number % 254) + 1),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function routerSessions(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $number): array => [
                '.id' => '*'.strtoupper(dechex($number)),
                'name' => 'pelanggan-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'service' => 'pppoe',
                'address' => '10.20.'.intdiv($number, 254).'.'.(($number % 254) + 1),
                'caller-id' => 'AA:BB:CC:DD:'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'uptime' => '1h',
            ])
            ->all();
    }
}
