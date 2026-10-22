<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\NetworkLink;
use App\Models\NetworkNode;
use App\Models\NetworkPort;
use App\Models\OntDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NetworkTopologyControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_and_customer_role_is_forbidden(): void
    {
        $this->get(route('network.topology'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'pelanggan']))
            ->get(route('network.topology'))
            ->assertForbidden();
    }

    public function test_owner_admin_and_technician_can_view_topology_without_calling_genieacs(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $server = NetworkNode::factory()->ofType('server')->create(['name' => 'Server Utama']);

        foreach (['owner', 'admin', 'teknisi'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('network.topology'))
                ->assertOk()
                ->assertSee('Server Utama')
                ->assertSee('Topologi PON');
        }

        Http::assertNothingSent();
        $this->assertNotNull($server);
    }

    public function test_admin_sees_no_edit_forms_and_cannot_write(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $odc = NetworkNode::factory()->ofType('odc')->create();

        $this->actingAs($admin)
            ->get(route('network.topology'))
            ->assertOk()
            ->assertDontSee('Tambah Node')
            ->assertDontSee('Simpan Node');
        $this->actingAs($admin)
            ->post(route('network.nodes.store'), ['type' => 'olt', 'name' => 'OLT Baru'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->patch(route('network.nodes.update', $odc), ['name' => 'Ganti'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('network.nodes.ports.store', $odc), ['port_number' => 1, 'port_type' => 'output'])
            ->assertForbidden();

        $this->assertDatabaseMissing('network_nodes', ['name' => 'OLT Baru']);
        $this->assertNotSame('Ganti', $odc->fresh()->name);
    }

    public function test_customer_role_cannot_write_topology(): void
    {
        $user = User::factory()->create(['role' => 'pelanggan']);

        $this->actingAs($user)
            ->post(route('network.nodes.store'), ['type' => 'olt', 'name' => 'OLT'])
            ->assertForbidden();
    }

    public function test_technician_creates_node_tree_with_audit_trail(): void
    {
        $technician = User::factory()->create(['role' => 'teknisi']);

        $this->actingAs($technician)
            ->post(route('network.nodes.store'), ['type' => 'server', 'name' => 'Server A'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $server = NetworkNode::query()->where('name', 'Server A')->firstOrFail();

        $this->actingAs($technician)
            ->post(route('network.nodes.store'), [
                'type' => 'mikrotik',
                'name' => 'Router A',
                'parent_id' => $server->id,
                'latitude' => '-6.95',
                'longitude' => '109.93',
                'technology' => 'gpon',
            ])
            ->assertSessionHasNoErrors();

        $router = NetworkNode::query()->where('name', 'Router A')->firstOrFail();
        $this->assertSame($server->id, $router->parent_id);
        $this->assertSame(-6.95, $router->latitude);
        $this->assertDatabaseHas('network_links', ['from_node_id' => $server->id, 'to_node_id' => $router->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'network.node.created',
            'auditable_id' => $router->id,
            'user_id' => $technician->id,
        ]);
    }

    public function test_store_node_rejects_invalid_input_and_invalid_transitions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $olt = NetworkNode::factory()->ofType('olt')->create();

        $this->actingAs($owner)
            ->post(route('network.nodes.store'), ['type' => 'router-ajaib', 'name' => 'X'])
            ->assertSessionHasErrors('type');
        $this->actingAs($owner)
            ->post(route('network.nodes.store'), ['type' => 'olt', 'name' => 'X', 'latitude' => 95, 'longitude' => 0])
            ->assertSessionHasErrors('latitude');
        $this->actingAs($owner)
            ->post(route('network.nodes.store'), ['type' => 'odp', 'name' => 'X', 'parent_id' => $olt->id])
            ->assertSessionHasErrors('parent_id');
        $this->actingAs($owner)
            ->post(route('network.nodes.store'), ['type' => 'ont', 'name' => 'ONT tanpa perangkat'])
            ->assertSessionHasErrors('ont_device_id');
        $this->actingAs($owner)
            ->post(route('network.nodes.store'), ['type' => 'olt', 'name' => 'X', 'latitude' => -6.9])
            ->assertSessionHasErrors('latitude');
    }

    public function test_owner_creates_ont_node_linked_to_a_device(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $odp = NetworkNode::factory()->ofType('odp')->create();
        $device = OntDevice::factory()->create();

        $this->actingAs($owner)
            ->post(route('network.nodes.store'), [
                'type' => 'ont',
                'name' => 'ONT Rumah A',
                'parent_id' => $odp->id,
                'ont_device_id' => $device->id,
            ])
            ->assertSessionHasNoErrors();

        $node = NetworkNode::query()->where('name', 'ONT Rumah A')->firstOrFail();
        $this->assertSame($node->id, $device->fresh()->network_node_id);
    }

    public function test_update_node_can_reparent_and_reject_cycle_via_http(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $odc = NetworkNode::factory()->ofType('odc')->create();
        $odpOne = NetworkNode::factory()->ofType('odp')->create(['parent_id' => $odc->id]);
        NetworkLink::query()->create(['from_node_id' => $odc->id, 'to_node_id' => $odpOne->id, 'status' => 'unknown']);
        $odpTwo = NetworkNode::factory()->ofType('odp')->create(['parent_id' => $odpOne->id]);
        NetworkLink::query()->create(['from_node_id' => $odpOne->id, 'to_node_id' => $odpTwo->id, 'status' => 'unknown']);

        $this->actingAs($owner)
            ->patch(route('network.nodes.update', $odpOne), ['parent_id' => $odpTwo->id])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($owner)
            ->patch(route('network.nodes.update', $odpTwo), [
                'name' => 'ODP Dua Baru',
                'is_active' => '0',
                'parent_id' => $odc->id,
            ])
            ->assertSessionHasNoErrors();

        $odpTwo->refresh();
        $this->assertSame('ODP Dua Baru', $odpTwo->name);
        $this->assertFalse($odpTwo->is_active);
        $this->assertSame($odc->id, $odpTwo->parent_id);
    }

    public function test_link_update_saves_waypoints_and_rejects_bad_ones(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $link = NetworkLink::factory()->create();

        $this->actingAs($owner)
            ->patch(route('network.links.update', $link), [
                'path_coordinates' => json_encode([[-6.95, 109.93], [-6.96, 109.94]]),
                'cable_length_m' => '250.5',
                'measured_attenuation_db' => '1.25',
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $link->refresh();
        $this->assertSame([[-6.95, 109.93], [-6.96, 109.94]], $link->path_coordinates);
        $this->assertSame(250.5, $link->cable_length_m);
        $this->assertSame('active', $link->status);

        $this->actingAs($owner)
            ->patch(route('network.links.update', $link), ['path_coordinates' => '{bukan json'])
            ->assertSessionHasErrors('path_coordinates');
        $this->actingAs($owner)
            ->patch(route('network.links.update', $link), ['path_coordinates' => json_encode([[200, 10]])])
            ->assertSessionHasErrors('path_coordinates');
        $this->actingAs($owner)
            ->patch(route('network.links.update', $link), ['path_coordinates' => '"teks"'])
            ->assertSessionHasErrors('path_coordinates');
        $this->actingAs($owner)
            ->patch(route('network.links.update', $link), ['status' => 'rusak'])
            ->assertSessionHasErrors('status');
        $this->actingAs($owner)
            ->patch(route('network.links.update', $link), ['measured_attenuation_db' => '-3'])
            ->assertSessionHasErrors('measured_attenuation_db');

        $this->assertSame([[-6.95, 109.93], [-6.96, 109.94]], $link->fresh()->path_coordinates);
    }

    public function test_store_link_connects_orphan_and_rejects_second_parent(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $odcOne = NetworkNode::factory()->ofType('odc')->create();
        $odcTwo = NetworkNode::factory()->ofType('odc')->create();
        $orphan = NetworkNode::factory()->ofType('odp')->create();

        $this->actingAs($owner)
            ->post(route('network.links.store'), ['from_node_id' => $odcOne->id, 'to_node_id' => $orphan->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($odcOne->id, $orphan->fresh()->parent_id);

        $this->actingAs($owner)
            ->post(route('network.links.store'), ['from_node_id' => $odcTwo->id, 'to_node_id' => $orphan->id])
            ->assertSessionHasErrors('to_node_id');
        $this->actingAs($owner)
            ->post(route('network.links.store'), ['from_node_id' => $orphan->id, 'to_node_id' => $orphan->id])
            ->assertSessionHasErrors('to_node_id');

        $this->assertSame(1, NetworkLink::query()->where('to_node_id', $orphan->id)->count());
    }

    public function test_store_port_validates_and_rejects_duplicates(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $odc = NetworkNode::factory()->ofType('odc')->create();

        $this->actingAs($owner)
            ->post(route('network.nodes.ports.store', $odc), ['port_number' => 1, 'port_type' => 'output', 'capacity' => 8])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)
            ->post(route('network.nodes.ports.store', $odc), ['port_number' => 1, 'port_type' => 'output'])
            ->assertSessionHasErrors('port_number');
        $this->actingAs($owner)
            ->post(route('network.nodes.ports.store', $odc), ['port_number' => 0, 'port_type' => 'tidak-ada'])
            ->assertSessionHasErrors(['port_number', 'port_type']);

        $this->assertSame(1, NetworkPort::query()->where('network_node_id', $odc->id)->count());
    }

    public function test_page_embeds_graph_focus_and_customer_data_without_secrets(): void
    {
        $customer = Customer::factory()->create(['name' => 'Pelanggan Topologi']);
        $odp = NetworkNode::factory()->ofType('odp')->create();
        $device = OntDevice::factory()->create(['customer_id' => $customer->id, 'serial_number' => 'TOPOSERIAL01']);
        $ontNode = NetworkNode::factory()->ofType('ont', $odp)->create(['name' => 'ONT Fokus']);
        $device->update(['network_node_id' => $ontNode->id]);

        $response = $this->actingAs(User::factory()->create(['role' => 'teknisi']))
            ->get(route('network.topology', ['focus' => $ontNode->id]))
            ->assertOk()
            ->assertSee('Pelanggan Topologi')
            ->assertSee('TOPOSERIAL01');

        $this->assertStringContainsString('const focusId = '.$ontNode->id.';', $response->getContent());
        $this->assertStringNotContainsStringIgnoringCase('password', $response->getContent());
    }

    public function test_focus_parameter_must_be_an_integer(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get(route('network.topology', ['focus' => 'abc']))
            ->assertSessionHasErrors('focus');
    }

    public function test_ont_detail_shows_topology_path(): void
    {
        $server = NetworkNode::factory()->ofType('server')->create(['name' => 'Server Pusat']);
        $olt = NetworkNode::factory()->ofType('olt', $server)->create(['name' => 'OLT Desa']);
        $odp = NetworkNode::factory()->ofType('odp')->create(['name' => 'ODP 07', 'parent_id' => $olt->id]);
        $ontNode = NetworkNode::factory()->ofType('ont', $odp)->create(['name' => 'ONT X']);
        $device = OntDevice::factory()->create(['network_node_id' => $ontNode->id]);
        $unplaced = OntDevice::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('ont-devices.show', $device))
            ->assertOk()
            ->assertSee('Posisi Topologi')
            ->assertSeeInOrder(['Server Pusat', 'OLT Desa', 'ODP 07', 'ONT X'])
            ->assertSee('Lihat di peta topologi');
        $this->actingAs($owner)
            ->get(route('ont-devices.show', $unplaced))
            ->assertOk()
            ->assertSee('belum dipasang pada topologi');
    }

    public function test_mapping_and_sync_keep_topology_placement(): void
    {
        $node = NetworkNode::factory()->ofType('ont')->create();
        $device = OntDevice::factory()->create(['network_node_id' => $node->id]);

        $device->fill(['connection_status' => 'offline', 'last_synced_at' => now()])->save();

        $this->assertSame($node->id, $device->fresh()->network_node_id);
    }
}
