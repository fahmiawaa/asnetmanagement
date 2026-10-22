<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\MikrotikDevice;
use App\Models\OntDevice;
use App\Models\PppoeAccount;
use App\Models\PppoeActiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OntDeviceMappingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_owner_and_technician_can_map_an_ont_manually_with_audit_trail(): void
    {
        foreach (['owner', 'teknisi'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $ont = OntDevice::factory()->create();
            $customer = Customer::factory()->create();

            $this->actingAs($user)
                ->patch(route('ont-devices.mapping.update', $ont), [
                    'customer_id' => $customer->id,
                    'mapping_method' => 'pppoe_mac',
                    'mapping_confidence' => 5,
                ])
                ->assertRedirect(route('ont-devices.show', $ont))
                ->assertSessionHas('success');

            $ont->refresh();
            $this->assertSame($customer->id, $ont->customer_id);
            $this->assertSame($user->id, $ont->mapped_by);
            $this->assertSame('manual', $ont->mapping_method);
            $this->assertSame(100, $ont->mapping_confidence);
            $this->assertNotNull($ont->mapped_at);
            $this->assertDatabaseHas('audit_logs', [
                'action' => 'ont.mapping.created',
                'auditable_id' => $ont->id,
                'user_id' => $user->id,
            ]);
        }
    }

    public function test_admin_has_read_only_access_to_ont_mapping(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::factory()->create();
        $ont = OntDevice::factory()->create();
        $mapped = OntDevice::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('ont-devices.mapping.update', $ont), ['customer_id' => $customer->id])
            ->assertForbidden();
        $this->actingAs($admin)
            ->delete(route('ont-devices.mapping.destroy', $mapped))
            ->assertForbidden();

        $this->assertNull($ont->fresh()->customer_id);
        $this->assertNotNull($mapped->fresh()->customer_id);
    }

    public function test_customer_role_cannot_change_mapping(): void
    {
        $user = User::factory()->create(['role' => 'pelanggan']);
        $ont = OntDevice::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)
            ->patch(route('ont-devices.mapping.update', $ont), ['customer_id' => $customer->id])
            ->assertForbidden();
    }

    public function test_rejects_customer_that_already_has_a_primary_ont(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $customer = Customer::factory()->create();
        OntDevice::factory()->create(['customer_id' => $customer->id]);
        $freeOnt = OntDevice::factory()->create();

        $this->actingAs($owner)
            ->patch(route('ont-devices.mapping.update', $freeOnt), ['customer_id' => $customer->id])
            ->assertSessionHasErrors('customer_id');

        $this->assertNull($freeOnt->fresh()->customer_id);
    }

    public function test_rejects_ont_that_is_already_mapped_to_another_customer(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $first = Customer::factory()->create();
        $second = Customer::factory()->create();
        $ont = OntDevice::factory()->create(['customer_id' => $first->id]);

        $this->actingAs($owner)
            ->patch(route('ont-devices.mapping.update', $ont), ['customer_id' => $second->id])
            ->assertSessionHasErrors('customer_id');

        $this->assertSame($first->id, $ont->fresh()->customer_id);
    }

    public function test_rejects_unknown_or_deleted_customers(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $ont = OntDevice::factory()->create();
        $deleted = Customer::factory()->create();
        $deleted->delete();

        $this->actingAs($owner)
            ->patch(route('ont-devices.mapping.update', $ont), ['customer_id' => 999999])
            ->assertSessionHasErrors('customer_id');
        $this->actingAs($owner)
            ->patch(route('ont-devices.mapping.update', $ont), ['customer_id' => $deleted->id])
            ->assertSessionHasErrors('customer_id');

        $this->assertNull($ont->fresh()->customer_id);
    }

    public function test_unmapping_clears_metadata_and_writes_audit_log(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $customer = Customer::factory()->create();
        $ont = OntDevice::factory()->create([
            'customer_id' => $customer->id,
            'mapped_by' => $owner->id,
            'mapped_at' => now(),
            'mapping_method' => 'manual',
            'mapping_confidence' => 100,
        ]);

        $this->actingAs($owner)
            ->delete(route('ont-devices.mapping.destroy', $ont))
            ->assertRedirect(route('ont-devices.show', $ont))
            ->assertSessionHas('success');

        $ont->refresh();
        $this->assertNull($ont->customer_id);
        $this->assertNull($ont->mapped_by);
        $this->assertNull($ont->mapped_at);
        $this->assertNull($ont->mapping_method);
        $this->assertNull($ont->mapping_confidence);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ont.mapping.removed',
            'auditable_id' => $ont->id,
        ]);
    }

    public function test_mapping_survives_synchronization_of_the_same_device(): void
    {
        $customer = Customer::factory()->create();
        $ont = OntDevice::factory()->create([
            'customer_id' => $customer->id,
            'mapping_method' => 'manual',
            'mapping_confidence' => 100,
        ]);

        $ont->fill(['connection_status' => 'offline', 'last_synced_at' => now()])->save();

        $this->assertSame($customer->id, $ont->fresh()->customer_id);
        $this->assertSame('manual', $ont->fresh()->mapping_method);
    }

    public function test_detail_suggests_and_records_pppoe_match_as_method_and_confidence(): void
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
            'username' => 'siti-pppoe-01',
        ]);
        $customer = Customer::factory()->create([
            'name' => 'Siti Aminah',
            'pppoe_account_id' => $account->id,
        ]);
        PppoeActiveSession::query()->create([
            'mikrotik_device_id' => $router->id,
            'router_session_id' => '*1A',
            'username' => 'siti-pppoe-01',
            'address' => '10.20.0.5',
            'caller_id' => 'aa:bb:cc:dd:ee:ff',
            'is_active' => true,
            'last_synced_at' => now(),
        ]);
        $ont = OntDevice::factory()->create([
            'pppoe_ip' => '10.20.0.5',
            'pppoe_mac' => 'AABB.CCDD.EEFF',
        ]);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('ont-devices.show', $ont))
            ->assertOk()
            ->assertSee('Kandidat dari sesi PPPoE aktif')
            ->assertSee('Siti Aminah')
            ->assertSee('95%');

        $this->actingAs($owner)
            ->patch(route('ont-devices.mapping.update', $ont), ['customer_id' => $customer->id])
            ->assertSessionHasNoErrors();

        $ont->refresh();
        $this->assertSame('pppoe_mac_ip', $ont->mapping_method);
        $this->assertSame(95, $ont->mapping_confidence);
    }

    public function test_admin_does_not_see_mapping_controls(): void
    {
        $ont = OntDevice::factory()->create();
        Customer::factory()->create(['name' => 'Pelanggan Bebas']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('ont-devices.show', $ont))
            ->assertOk()
            ->assertSee('Hanya owner dan teknisi yang dapat mengubah mapping.')
            ->assertDontSee('Pelanggan Bebas');
    }

    public function test_customer_detail_shows_mapped_ont_card(): void
    {
        $customer = Customer::factory()->create();
        OntDevice::factory()->create([
            'customer_id' => $customer->id,
            'serial_number' => 'CARDSERIAL01',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Perangkat ONT')
            ->assertSee('CARDSERIAL01');
    }
}
