<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\MikrotikDevice;
use App\Models\OntDevice;
use App\Models\PppoeAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OntDeviceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('ont-devices.index'))->assertRedirect(route('login'));
    }

    public function test_customer_role_cannot_open_ont_pages(): void
    {
        $customerUser = User::factory()->create(['role' => 'pelanggan']);
        $ont = OntDevice::factory()->create();

        $this->actingAs($customerUser)->get(route('ont-devices.index'))->assertForbidden();
        $this->actingAs($customerUser)->get(route('ont-devices.show', $ont))->assertForbidden();
    }

    public function test_owner_admin_and_technician_can_view_list_and_detail(): void
    {
        $ont = OntDevice::factory()->create(['serial_number' => 'VIEWSERIAL01']);

        foreach (['owner', 'admin', 'teknisi'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('ont-devices.index'))
                ->assertOk()
                ->assertSee('VIEWSERIAL01');
            $this->actingAs($user)
                ->get(route('ont-devices.show', $ont))
                ->assertOk()
                ->assertSee('VIEWSERIAL01');
        }
    }

    public function test_list_reads_local_snapshot_and_never_calls_genieacs(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        OntDevice::factory()->count(3)->create();

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get(route('ont-devices.index'))
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_search_covers_serial_customer_and_pppoe_username(): void
    {
        $router = MikrotikDevice::query()->create([
            'name' => 'Router A',
            'host' => '10.10.10.1',
            'api_port' => 8728,
            'use_ssl' => false,
            'username' => 'api-user',
            'encrypted_password' => 'encrypted-test-value',
        ]);
        $account = PppoeAccount::query()->create([
            'mikrotik_device_id' => $router->id,
            'username' => 'budi-pppoe-01',
        ]);
        $customer = Customer::factory()->create([
            'name' => 'Budi Santoso',
            'customer_code' => 'CUST-7001',
            'pppoe_account_id' => $account->id,
        ]);
        OntDevice::factory()->create([
            'customer_id' => $customer->id,
            'serial_number' => 'MAPPEDONT001',
        ]);
        OntDevice::factory()->create(['serial_number' => 'OTHERONT0001']);
        $owner = User::factory()->create(['role' => 'owner']);

        foreach (['MAPPEDONT001', 'Budi Santoso', 'CUST-7001', 'budi-pppoe-01'] as $term) {
            $this->actingAs($owner)
                ->get(route('ont-devices.index', ['q' => $term]))
                ->assertOk()
                ->assertSee('MAPPEDONT001')
                ->assertDontSee('OTHERONT0001');
        }
    }

    public function test_filters_by_status_quality_and_mapping(): void
    {
        $customer = Customer::factory()->create();
        OntDevice::factory()->create([
            'serial_number' => 'ONLINEMAPPED',
            'connection_status' => 'online',
            'rx_power_quality' => 'good',
            'customer_id' => $customer->id,
        ]);
        OntDevice::factory()->create([
            'serial_number' => 'OFFLINEFREE01',
            'connection_status' => 'offline',
            'rx_power_quality' => 'critical',
        ]);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['status' => 'offline']))
            ->assertSee('OFFLINEFREE01')
            ->assertDontSee('ONLINEMAPPED');
        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['quality' => 'critical']))
            ->assertSee('OFFLINEFREE01')
            ->assertDontSee('ONLINEMAPPED');
        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['mapping' => 'unmapped']))
            ->assertSee('OFFLINEFREE01')
            ->assertDontSee('ONLINEMAPPED');
        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['mapping' => 'mapped']))
            ->assertSee('ONLINEMAPPED')
            ->assertDontSee('OFFLINEFREE01');
    }

    public function test_rejects_sort_and_filter_values_outside_the_allowlist(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['sort' => 'id; DROP TABLE ont_devices']))
            ->assertSessionHasErrors('sort');
        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['direction' => 'sideways']))
            ->assertSessionHasErrors('direction');
        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['status' => 'exploded']))
            ->assertSessionHasErrors('status');
    }

    public function test_sorting_by_rx_power_puts_invalid_readings_last(): void
    {
        OntDevice::factory()->create(['serial_number' => 'RXNULLONT001', 'rx_power_dbm' => null]);
        OntDevice::factory()->create(['serial_number' => 'RXWEAKONT001', 'rx_power_dbm' => -30.00]);
        OntDevice::factory()->create(['serial_number' => 'RXGOODONT001', 'rx_power_dbm' => -20.00]);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['sort' => 'rx_power', 'direction' => 'asc']))
            ->assertSeeInOrder(['RXWEAKONT001', 'RXGOODONT001', 'RXNULLONT001']);
        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['sort' => 'rx_power', 'direction' => 'desc']))
            ->assertSeeInOrder(['RXGOODONT001', 'RXWEAKONT001', 'RXNULLONT001']);
    }

    public function test_sorting_by_customer_and_status_runs_without_error(): void
    {
        OntDevice::factory()->count(3)->create();
        $owner = User::factory()->create(['role' => 'owner']);

        foreach (['customer', 'status', 'serial', 'manufacturer', 'product_class', 'pon_mode', 'last_inform'] as $sort) {
            foreach (['asc', 'desc'] as $direction) {
                $this->actingAs($owner)
                    ->get(route('ont-devices.index', ['sort' => $sort, 'direction' => $direction]))
                    ->assertOk();
            }
        }
    }

    public function test_list_is_paginated_server_side(): void
    {
        config()->set('genieacs.ont_list_per_page', 2);
        foreach (['PAGEONT0001', 'PAGEONT0002', 'PAGEONT0003'] as $serial) {
            OntDevice::factory()->create(['serial_number' => $serial]);
        }
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('ont-devices.index', ['sort' => 'serial', 'direction' => 'asc', 'page' => 2]))
            ->assertOk()
            ->assertSee('PAGEONT0003')
            ->assertDontSee('PAGEONT0001');
    }

    public function test_detail_shows_live_status_and_flags_stale_snapshot(): void
    {
        $ont = OntDevice::factory()->create([
            'inform_interval_seconds' => 200,
            'last_inform_at' => now()->subHours(3),
            'connection_status' => 'online',
            'rx_power_raw' => 'N/A',
            'rx_power_dbm' => null,
            'rx_power_quality' => 'unknown',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'teknisi']))
            ->get(route('ont-devices.show', $ont))
            ->assertOk()
            ->assertSee('Snapshot Sinkron')
            ->assertSee('Tidak valid / tidak tersedia')
            ->assertSee('N/A');
    }

    public function test_list_serves_three_hundred_devices_within_performance_budget(): void
    {
        $this->seedThreeHundredMappedDevices();

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('ont-devices.index'))
            ->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $durations = [];

        for ($sample = 0; $sample < 5; $sample++) {
            $startedAt = microtime(true);

            $this->actingAs($owner)
                ->get(route('ont-devices.index'))
                ->assertOk()
                ->assertSee('LOADONT0');

            $durations[] = (microtime(true) - $startedAt) * 1000;
        }

        sort($durations);

        $p95 = $durations[(int) ceil(0.95 * count($durations)) - 1];

        $this->assertLessThan(
            2000.0,
            $p95,
            "p95 daftar 300 ONT {$p95} ms melebihi target 2 detik."
        );
        $this->assertLessThanOrEqual(
            100,
            $queries,
            "Daftar 300 ONT menjalankan {$queries} query untuk 5 request, indikasi N+1."
        );
    }

    private function seedThreeHundredMappedDevices(): void
    {
        $package = InternetPackage::factory()->create();
        $router = MikrotikDevice::query()->create([
            'name' => 'Router Load Test',
            'host' => '10.10.99.1',
            'api_port' => 8728,
            'use_ssl' => false,
            'username' => 'load-test',
            'encrypted_password' => 'encrypted-test-value',
        ]);

        $statuses = ['online', 'warning', 'offline', 'unknown'];
        $qualities = ['good', 'warning', 'critical', 'overload'];

        for ($index = 1; $index <= 300; $index++) {
            $account = PppoeAccount::query()->create([
                'mikrotik_device_id' => $router->id,
                'username' => sprintf('load-%04d', $index),
            ]);

            $customer = Customer::factory()->create([
                'customer_code' => sprintf('LOAD-%04d', $index),
                'name' => sprintf('Pelanggan Beban %03d', $index),
                'internet_package_id' => $package->id,
                'pppoe_account_id' => $account->id,
            ]);

            OntDevice::factory()->create([
                'customer_id' => $customer->id,
                'genieacs_device_id' => sprintf('LOAD-DEV-%04d', $index),
                'serial_number' => sprintf('LOADONT%05d', $index),
                'connection_status' => $statuses[$index % 4],
                'rx_power_quality' => $qualities[$index % 4],
                'last_inform_at' => now()->subSeconds($index * 10),
            ]);
        }
    }
}
