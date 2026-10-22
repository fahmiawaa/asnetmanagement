<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\OntDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpticalAlertControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('optical-alerts.index'))->assertRedirect(route('login'));
    }

    public function test_customer_role_cannot_open_alert_page(): void
    {
        $user = User::factory()->create(['role' => 'pelanggan']);

        $this->actingAs($user)->get(route('optical-alerts.index'))->assertForbidden();
    }

    public function test_owner_admin_and_technician_can_open_alert_page(): void
    {
        foreach (['owner', 'admin', 'teknisi'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('optical-alerts.index'))->assertOk();
        }
    }

    public function test_page_lists_offline_and_critical_devices_only(): void
    {
        OntDevice::factory()->create([
            'serial_number' => 'ALERTOFF0001',
            'connection_status' => 'offline',
            'rx_power_quality' => 'good',
        ]);
        OntDevice::factory()->create([
            'serial_number' => 'ALERTRX00001',
            'connection_status' => 'online',
            'rx_power_quality' => 'critical',
        ]);
        OntDevice::factory()->create([
            'serial_number' => 'ALERTOK00001',
            'connection_status' => 'online',
            'rx_power_quality' => 'good',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'teknisi']))
            ->get(route('optical-alerts.index'))
            ->assertOk()
            ->assertSee('ALERTOFF0001')
            ->assertSee('ALERTRX00001')
            ->assertDontSee('ALERTOK00001');
    }

    public function test_type_filter_narrows_the_list(): void
    {
        OntDevice::factory()->create([
            'serial_number' => 'FILTEROFF001',
            'connection_status' => 'offline',
            'rx_power_quality' => 'good',
        ]);
        OntDevice::factory()->create([
            'serial_number' => 'FILTERRX00001',
            'connection_status' => 'online',
            'rx_power_quality' => 'overload',
        ]);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('optical-alerts.index', ['type' => 'offline']))
            ->assertSee('FILTEROFF001')
            ->assertDontSee('FILTERRX00001');

        $this->actingAs($owner)
            ->get(route('optical-alerts.index', ['type' => 'optical']))
            ->assertSee('FILTERRX00001')
            ->assertDontSee('FILTEROFF001');
    }

    public function test_rejects_unknown_filter_value(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('optical-alerts.index', ['type' => 'everything']))
            ->assertSessionHasErrors('type');
    }

    public function test_page_reads_local_snapshot_and_never_calls_genieacs(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        OntDevice::factory()->create(['connection_status' => 'offline']);

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get(route('optical-alerts.index'))
            ->assertOk();

        Http::assertNothingSent();
    }
}
