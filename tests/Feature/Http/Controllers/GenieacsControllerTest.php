<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\GenieacsServer;
use App\Models\OntDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenieacsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('genieacs.index'))->assertRedirect(route('login'));
    }

    public function test_technician_and_customer_cannot_open_genieacs_page(): void
    {
        foreach (['teknisi', 'pelanggan'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('genieacs.index'))->assertForbidden();
        }
    }

    public function test_owner_and_admin_can_view_the_page(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('genieacs.index'))->assertOk();
        }
    }

    public function test_admin_cannot_change_genieacs_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $server = GenieacsServer::factory()->create();

        $this->actingAs($admin)
            ->post(route('genieacs.store'), ['name' => 'X', 'base_url' => 'http://genieacs:7557'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('genieacs.test'), ['server_id' => $server->id])
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('genieacs.sync'))
            ->assertForbidden();
    }

    public function test_owner_can_store_server_with_encrypted_password(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->post(route('genieacs.store'), [
                'name' => 'GenieACS Utama',
                'base_url' => 'http://genieacs:7557/',
                'username' => 'admin',
                'password' => 'rahasia-nbi',
                'is_active' => '1',
            ])
            ->assertRedirect(route('genieacs.index'))
            ->assertSessionHas('success');

        $server = GenieacsServer::query()->firstOrFail();

        $this->assertSame('GenieACS Utama', $server->name);
        $this->assertSame('http://genieacs:7557', $server->base_url);
        $this->assertSame('rahasia-nbi', $server->encrypted_password);
        $this->assertNotSame('rahasia-nbi', $server->getRawOriginal('encrypted_password'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'genieacs.server.created']);
    }

    public function test_store_rejects_non_http_url(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->from(route('genieacs.index'))
            ->post(route('genieacs.store'), [
                'name' => 'Buruk',
                'base_url' => 'javascript:alert(1)',
            ])
            ->assertRedirect(route('genieacs.index'))
            ->assertSessionHasErrors('base_url');

        $this->assertDatabaseCount('genieacs_servers', 0);
    }

    public function test_connection_test_records_result_and_audit_log(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/devices*' => Http::response([], 200)]);

        $owner = User::factory()->create(['role' => 'owner']);
        $server = GenieacsServer::factory()->create(['connection_status' => 'unknown']);

        $this->actingAs($owner)
            ->post(route('genieacs.test'), ['server_id' => $server->id])
            ->assertRedirect(route('genieacs.index'))
            ->assertSessionHas('success');

        $this->assertSame('connected', $server->fresh()->connection_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'genieacs.connection.tested']);
    }

    public function test_connection_test_failure_is_reported_without_password_leak(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/devices*' => Http::response('unauthorized', 401)]);

        $owner = User::factory()->create(['role' => 'owner']);
        $server = GenieacsServer::factory()->create([
            'username' => 'admin',
            'encrypted_password' => 'super-secret',
        ]);

        $this->actingAs($owner)
            ->post(route('genieacs.test'), ['server_id' => $server->id])
            ->assertRedirect(route('genieacs.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertSame('failed', $server->fresh()->connection_status);
        $this->assertStringNotContainsString('super-secret', (string) session('error'));
    }

    public function test_sync_creates_ont_snapshot_from_nbi_payload(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/devices*' => Http::response([
                [
                    '_id' => 'aaaa-bbbb-cccc',
                    '_lastInform' => now()->subSeconds(30)->toIso8601String(),
                    '_deviceId' => [
                        '_Manufacturer' => 'ZTE',
                        '_OUI' => 'FFFFAA',
                        '_ProductClass' => 'F660',
                        '_SerialNumber' => 'ZTESYNC0001',
                    ],
                    'VirtualParameters' => [
                        'getSerialNumber' => ['_value' => 'ZTESYNC0001'],
                        'getponmode' => ['_value' => 'GPON'],
                        'RXPower' => ['_value' => '-20.5'],
                    ],
                    'InternetGatewayDevice' => [
                        'ManagementServer' => [
                            'PeriodicInformInterval' => ['_value' => 200],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create(['role' => 'owner']);
        $server = GenieacsServer::factory()->create();

        $this->actingAs($owner)
            ->post(route('genieacs.sync'))
            ->assertRedirect(route('genieacs.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, OntDevice::query()->count());
        $this->assertDatabaseHas('ont_devices', ['serial_number' => 'ZTESYNC0001']);
        $this->assertNotNull($server->fresh()->last_sync_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'genieacs.ont.synced']);
    }

    public function test_sync_failure_keeps_the_rest_of_the_page_usable(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/devices*' => Http::response('boom', 500)]);

        $owner = User::factory()->create(['role' => 'owner']);
        GenieacsServer::factory()->create();

        $this->actingAs($owner)
            ->post(route('genieacs.sync'))
            ->assertRedirect(route('genieacs.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, OntDevice::query()->count());
    }

    public function test_sync_without_active_server_warns_the_operator(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        GenieacsServer::factory()->create(['is_active' => false]);

        $this->actingAs($owner)
            ->post(route('genieacs.sync'))
            ->assertRedirect(route('genieacs.index'))
            ->assertSessionHas('error');
    }
}
