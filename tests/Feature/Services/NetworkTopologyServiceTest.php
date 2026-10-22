<?php

namespace Tests\Feature\Services;

use App\Models\Customer;
use App\Models\NetworkLink;
use App\Models\NetworkNode;
use App\Models\NetworkPort;
use App\Models\OntDevice;
use App\Models\User;
use App\Services\NetworkTopologyService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NetworkTopologyServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private NetworkTopologyService $service;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(NetworkTopologyService::class);
        $this->actor = User::factory()->create(['role' => 'owner']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validTransitions(): array
    {
        return [
            'mikrotik di server' => ['mikrotik', 'server'],
            'olt di server' => ['olt', 'server'],
            'olt di mikrotik' => ['olt', 'mikrotik'],
            'pon di olt' => ['pon_port', 'olt'],
            'odc di pon' => ['odc', 'pon_port'],
            'odp di odc' => ['odp', 'odc'],
            'odp di odp' => ['odp', 'odp'],
            'ont di odp' => ['ont', 'odp'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidTransitions(): array
    {
        return [
            'mikrotik di olt' => ['mikrotik', 'olt'],
            'olt di odp' => ['olt', 'odp'],
            'pon di server' => ['pon_port', 'server'],
            'odc di olt' => ['odc', 'olt'],
            'odp di pon' => ['odp', 'pon_port'],
            'ont di odc' => ['ont', 'odc'],
            'ont di ont' => ['ont', 'ont'],
            'server di mikrotik' => ['server', 'mikrotik'],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_accepts_allowed_parent_child_transitions(string $childType, string $parentType): void
    {
        $parent = NetworkNode::factory()->ofType($parentType)->create();
        $data = ['type' => $childType, 'name' => 'Child', 'parent_id' => $parent->id];

        if ($childType === 'ont') {
            $data['ont_device_id'] = OntDevice::factory()->create()->id;
        }

        $child = $this->service->createNode($data, $this->actor);

        $this->assertSame($parent->id, $child->parent_id);
        $this->assertDatabaseHas('network_links', [
            'from_node_id' => $parent->id,
            'to_node_id' => $child->id,
        ]);
    }

    #[DataProvider('invalidTransitions')]
    public function test_rejects_disallowed_parent_child_transitions(string $childType, string $parentType): void
    {
        $parent = NetworkNode::factory()->ofType($parentType)->create();

        $this->assertRejected('parent_id', fn () => $this->service->createNode([
            'type' => $childType,
            'name' => 'Child',
            'parent_id' => $parent->id,
        ], $this->actor));
        $this->assertDatabaseCount('network_links', 0);
    }

    public function test_node_without_parent_is_allowed_as_unconnected_orphan(): void
    {
        $node = $this->service->createNode(['type' => 'odc', 'name' => 'ODC Yatim'], $this->actor);

        $this->assertNull($node->parent_id);
        $this->assertDatabaseCount('network_links', 0);
    }

    public function test_rejects_unknown_parent_and_half_filled_coordinates(): void
    {
        $this->assertRejected('parent_id', fn () => $this->service->createNode([
            'type' => 'odc', 'name' => 'X', 'parent_id' => 999999,
        ], $this->actor));
        $this->assertRejected('latitude', fn () => $this->service->createNode([
            'type' => 'olt', 'name' => 'X', 'latitude' => -6.9, 'longitude' => null,
        ], $this->actor));
    }

    public function test_rejects_self_parent(): void
    {
        $node = NetworkNode::factory()->ofType('odp')->create();

        $this->assertRejected('parent_id', fn () => $this->service->updateNode(
            $node,
            ['parent_id' => $node->id],
            $this->actor
        ));
    }

    public function test_rejects_cycles_when_reparenting(): void
    {
        $a = NetworkNode::factory()->ofType('odp')->create();
        $b = $this->service->createNode(['type' => 'odp', 'name' => 'B', 'parent_id' => $a->id], $this->actor);
        $c = $this->service->createNode(['type' => 'odp', 'name' => 'C', 'parent_id' => $b->id], $this->actor);

        $this->assertRejected('parent_id', fn () => $this->service->updateNode(
            $a,
            ['parent_id' => $c->id],
            $this->actor
        ));

        $this->assertNull($a->fresh()->parent_id);
    }

    public function test_reparenting_moves_link_and_resets_source_port_and_waypoints(): void
    {
        $odcOne = NetworkNode::factory()->ofType('odc')->create();
        $odcTwo = NetworkNode::factory()->ofType('odc')->create();
        $odp = $this->service->createNode(['type' => 'odp', 'name' => 'ODP', 'parent_id' => $odcOne->id], $this->actor);
        $port = NetworkPort::factory()->create(['network_node_id' => $odcOne->id]);
        $link = NetworkLink::query()->where('to_node_id', $odp->id)->firstOrFail();
        $this->service->updateLink($link, [
            'from_port_id' => $port->id,
            'path_coordinates' => [[-6.9, 109.9]],
        ], $this->actor);

        $this->service->updateNode($odp, ['parent_id' => $odcTwo->id], $this->actor);

        $link->refresh();
        $this->assertSame($odcTwo->id, $link->from_node_id);
        $this->assertNull($link->from_port_id);
        $this->assertNull($link->path_coordinates);
        $this->assertSame($odcTwo->id, $odp->fresh()->parent_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.node.reparented']);
    }

    public function test_detaching_a_node_removes_its_incoming_link(): void
    {
        $odc = NetworkNode::factory()->ofType('odc')->create();
        $odp = $this->service->createNode(['type' => 'odp', 'name' => 'ODP', 'parent_id' => $odc->id], $this->actor);

        $this->service->updateNode($odp, ['parent_id' => null], $this->actor);

        $this->assertNull($odp->fresh()->parent_id);
        $this->assertDatabaseCount('network_links', 0);
    }

    public function test_server_cannot_be_given_a_parent(): void
    {
        $server = NetworkNode::factory()->ofType('server')->create();
        $other = NetworkNode::factory()->ofType('server')->create();

        $this->assertRejected('parent_id', fn () => $this->service->updateNode(
            $server,
            ['parent_id' => $other->id],
            $this->actor
        ));
    }

    public function test_ont_cannot_be_attached_to_two_odps(): void
    {
        $odpOne = NetworkNode::factory()->ofType('odp')->create();
        $odpTwo = NetworkNode::factory()->ofType('odp')->create();
        $ontNode = $this->service->createNode([
            'type' => 'ont',
            'name' => 'ONT',
            'parent_id' => $odpOne->id,
            'ont_device_id' => OntDevice::factory()->create()->id,
        ], $this->actor);

        $this->assertRejected('to_node_id', fn () => $this->service->createLink([
            'from_node_id' => $odpTwo->id,
            'to_node_id' => $ontNode->id,
        ], $this->actor));

        $this->assertSame(1, NetworkLink::query()->where('to_node_id', $ontNode->id)->count());
    }

    public function test_ont_device_can_only_be_placed_on_one_node_and_is_required(): void
    {
        $odp = NetworkNode::factory()->ofType('odp')->create();
        $device = OntDevice::factory()->create();
        $first = $this->service->createNode([
            'type' => 'ont', 'name' => 'ONT 1', 'parent_id' => $odp->id, 'ont_device_id' => $device->id,
        ], $this->actor);

        $this->assertSame($first->id, $device->fresh()->network_node_id);
        $this->assertRejected('ont_device_id', fn () => $this->service->createNode([
            'type' => 'ont', 'name' => 'ONT 2', 'parent_id' => $odp->id, 'ont_device_id' => $device->id,
        ], $this->actor));
        $this->assertRejected('ont_device_id', fn () => $this->service->createNode([
            'type' => 'ont', 'name' => 'ONT 3', 'parent_id' => $odp->id,
        ], $this->actor));
        $this->assertRejected('ont_device_id', fn () => $this->service->createNode([
            'type' => 'odp', 'name' => 'ODP 2', 'ont_device_id' => OntDevice::factory()->create()->id,
        ], $this->actor));
    }

    public function test_creating_link_connects_an_orphan_and_validates_the_transition(): void
    {
        $odc = NetworkNode::factory()->ofType('odc')->create();
        $orphanOdp = NetworkNode::factory()->ofType('odp')->create();
        $olt = NetworkNode::factory()->ofType('olt')->create();

        $this->assertRejected('parent_id', fn () => $this->service->createLink([
            'from_node_id' => $olt->id,
            'to_node_id' => $orphanOdp->id,
        ], $this->actor));

        $link = $this->service->createLink([
            'from_node_id' => $odc->id,
            'to_node_id' => $orphanOdp->id,
            'cable_length_m' => 120.5,
        ], $this->actor);

        $this->assertSame($odc->id, $orphanOdp->fresh()->parent_id);
        $this->assertSame(120.5, $link->cable_length_m);
    }

    public function test_port_must_belong_to_link_node_and_cannot_be_reused(): void
    {
        $odc = NetworkNode::factory()->ofType('odc')->create();
        $odpOne = $this->service->createNode(['type' => 'odp', 'name' => 'ODP 1', 'parent_id' => $odc->id], $this->actor);
        $odpTwo = $this->service->createNode(['type' => 'odp', 'name' => 'ODP 2', 'parent_id' => $odc->id], $this->actor);
        $odcPort = $this->service->createPort($odc, ['port_number' => 1, 'port_type' => 'output'], $this->actor);
        $strangerPort = NetworkPort::factory()->create();
        $linkOne = NetworkLink::query()->where('to_node_id', $odpOne->id)->firstOrFail();
        $linkTwo = NetworkLink::query()->where('to_node_id', $odpTwo->id)->firstOrFail();

        $this->assertRejected('from_port_id', fn () => $this->service->updateLink(
            $linkOne,
            ['from_port_id' => $strangerPort->id],
            $this->actor
        ));

        $this->service->updateLink($linkOne, ['from_port_id' => $odcPort->id], $this->actor);
        $this->assertSame($odcPort->id, $linkOne->fresh()->from_port_id);

        $this->assertRejected('from_port_id', fn () => $this->service->updateLink(
            $linkTwo,
            ['from_port_id' => $odcPort->id],
            $this->actor
        ));

        $this->service->updateLink($linkOne, ['from_port_id' => $odcPort->id, 'status' => 'active'], $this->actor);
        $this->assertSame('active', $linkOne->fresh()->status);
    }

    public function test_duplicate_port_number_and_type_is_rejected(): void
    {
        $odc = NetworkNode::factory()->ofType('odc')->create();
        $this->service->createPort($odc, ['port_number' => 3, 'port_type' => 'output'], $this->actor);

        $this->assertRejected('port_number', fn () => $this->service->createPort(
            $odc,
            ['port_number' => 3, 'port_type' => 'output'],
            $this->actor
        ));

        $this->service->createPort($odc, ['port_number' => 3, 'port_type' => 'input'], $this->actor);
        $this->assertSame(2, $odc->ports()->count());
    }

    public function test_waypoints_are_normalized_and_validated(): void
    {
        $link = NetworkLink::factory()->create();

        $this->service->updateLink($link, [
            'path_coordinates' => [['-6.95', '109.93'], [-6.951234567891, 109.94]],
        ], $this->actor);

        $this->assertSame([[-6.95, 109.93], [-6.9512346, 109.94]], $link->fresh()->path_coordinates);

        foreach ([
            [[91, 100]],
            [[-6.9, 181]],
            [['a', 'b']],
            [[-6.9]],
            ['bukan-array'],
            array_fill(0, 201, [-6.9, 109.9]),
        ] as $invalid) {
            $this->assertRejected('path_coordinates', fn () => $this->service->updateLink(
                $link,
                ['path_coordinates' => $invalid],
                $this->actor
            ));
        }

        $this->service->updateLink($link, ['path_coordinates' => []], $this->actor);
        $this->assertNull($link->fresh()->path_coordinates);
    }

    public function test_path_to_returns_chain_from_root(): void
    {
        $server = NetworkNode::factory()->ofType('server')->create(['name' => 'Server']);
        $olt = $this->service->createNode(['type' => 'olt', 'name' => 'OLT', 'parent_id' => $server->id], $this->actor);
        $pon = $this->service->createNode(['type' => 'pon_port', 'name' => 'PON 1', 'parent_id' => $olt->id], $this->actor);

        $path = $this->service->pathTo($pon);

        $this->assertSame(['server', 'olt', 'pon_port'], array_column($path, 'type'));
    }

    public function test_graph_aggregates_cable_quality_and_status_from_downstream_onts(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        $odc = NetworkNode::factory()->ofType('odc')->create(['latitude' => -6.95, 'longitude' => 109.93]);
        $odp = $this->service->createNode([
            'type' => 'odp', 'name' => 'ODP A', 'parent_id' => $odc->id, 'latitude' => -6.951, 'longitude' => 109.931,
        ], $this->actor);
        $onlineGood = OntDevice::factory()->create([
            'last_inform_at' => now()->subSeconds(60),
            'inform_interval_seconds' => 200,
            'rx_power_dbm' => -20,
            'rx_power_quality' => 'good',
        ]);
        $offlineCritical = OntDevice::factory()->create([
            'last_inform_at' => now()->subHours(2),
            'inform_interval_seconds' => 200,
            'rx_power_dbm' => -30,
            'rx_power_quality' => 'critical',
        ]);
        $customer = Customer::factory()->create(['latitude' => -6.9515, 'longitude' => 109.9315]);
        $offlineCritical->update(['customer_id' => $customer->id]);
        $ontOne = $this->service->createNode([
            'type' => 'ont', 'name' => 'ONT 1', 'parent_id' => $odp->id, 'ont_device_id' => $onlineGood->id,
            'latitude' => -6.9512, 'longitude' => 109.9312,
        ], $this->actor);
        $ontTwo = $this->service->createNode([
            'type' => 'ont', 'name' => 'ONT 2', 'parent_id' => $odp->id, 'ont_device_id' => $offlineCritical->id,
        ], $this->actor);

        $graph = $this->service->buildGraph();
        $links = collect($graph['links'])->keyBy('to_node_id');
        $nodes = collect($graph['nodes'])->keyBy('id');

        $feeder = $links[$odp->id];
        $this->assertSame('online', $feeder['acs_status']);
        $this->assertSame('critical', $feeder['quality']);
        $this->assertSame(2, $feeder['summary']['ont_total']);
        $this->assertSame(1, $feeder['summary']['offline']);

        $this->assertSame('online', $links[$ontOne->id]['acs_status']);
        $this->assertSame('good', $links[$ontOne->id]['quality']);
        $this->assertSame('offline', $links[$ontTwo->id]['acs_status']);
        $this->assertSame('critical', $links[$ontTwo->id]['quality']);

        $this->assertSame(-6.9515, $nodes[$ontTwo->id]['latitude']);
        $this->assertFalse($nodes[$ontTwo->id]['own_coordinates']);
        $this->assertTrue($nodes[$ontOne->id]['own_coordinates']);
        $this->assertSame(2, $graph['stats']['ont_nodes']);
        $this->assertSame(1, $graph['stats']['orphans']);
        $this->assertStringNotContainsStringIgnoringCase('password', json_encode($graph));
    }

    public function test_graph_marks_cable_offline_only_when_all_downstream_onts_are_offline(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        $odp = NetworkNode::factory()->ofType('odp')->create();
        $stale = OntDevice::factory()->create([
            'last_inform_at' => now()->subDay(),
            'inform_interval_seconds' => 200,
            'rx_power_quality' => 'unknown',
        ]);
        $this->service->createNode([
            'type' => 'ont', 'name' => 'ONT', 'parent_id' => $odp->id, 'ont_device_id' => $stale->id,
        ], $this->actor);
        $olt = NetworkNode::factory()->ofType('olt')->create();
        $pon = $this->service->createNode(['type' => 'pon_port', 'name' => 'PON', 'parent_id' => $olt->id], $this->actor);

        $graph = $this->service->buildGraph();
        $links = collect($graph['links'])->keyBy('to_node_id');

        $this->assertSame('offline', $links[$this->ontNodeId($graph)]['acs_status']);
        $this->assertSame('unknown', $links[$this->ontNodeId($graph)]['quality']);
        $this->assertSame('unknown', $links[$pon->id]['acs_status']);
        $this->assertSame(0, $links[$pon->id]['summary']['ont_total']);
    }

    /**
     * @param  array<string, mixed>  $graph
     */
    private function ontNodeId(array $graph): int
    {
        return (int) collect($graph['nodes'])->firstWhere('type', 'ont')['id'];
    }

    private function assertRejected(string $field, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());

            return;
        }

        $this->fail("Seharusnya ValidationException pada kolom {$field}.");
    }
}
