<?php

namespace App\Services;

use App\Models\NetworkLink;
use App\Models\NetworkNode;
use App\Models\NetworkPort;
use App\Models\OntDevice;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NetworkTopologyService
{
    /**
     * Relasi child => daftar tipe parent yang diperbolehkan.
     * Aturan longgar: semua tipe boleh menjadi parent untuk tipe lain.
     *
     * @var array<string, array<int, string>>
     */
    public const ALLOWED_PARENTS = [
        'server' => ['server', 'mikrotik', 'olt', 'pon_port', 'odc', 'odp', 'ont'],
        'mikrotik' => ['server', 'mikrotik', 'olt', 'pon_port', 'odc', 'odp', 'ont'],
        'olt' => ['server', 'mikrotik', 'olt', 'pon_port', 'odc', 'odp', 'ont'],
        'pon_port' => ['server', 'mikrotik', 'olt', 'pon_port', 'odc', 'odp', 'ont'],
        'odc' => ['server', 'mikrotik', 'olt', 'pon_port', 'odc', 'odp', 'ont'],
        'odp' => ['server', 'mikrotik', 'olt', 'pon_port', 'odc', 'odp', 'ont'],
        'ont' => ['server', 'mikrotik', 'olt', 'pon_port', 'odc', 'odp', 'ont'],
    ];

    private const QUALITY_SEVERITY = [
        'unknown' => 0,
        'good' => 1,
        'overload' => 2,
        'warning' => 3,
        'critical' => 4,
    ];

    private const MAX_WAYPOINTS = 200;

    private const EARTH_RADIUS_METERS = 6371008.8;

    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly OntStatusService $statusService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createNode(array $data, User $actor): NetworkNode
    {
        return DB::transaction(function () use ($data): NetworkNode {
            $type = (string) $data['type'];
            $parent = $this->findParent($data['parent_id'] ?? null);
            $this->assertParentAllowed($type, $parent, null);
            $this->assertCoordinatePair($data['latitude'] ?? null, $data['longitude'] ?? null);

            $ont = null;
            $ontDeviceId = $data['ont_device_id'] ?? null;

            if ($type === 'ont') {
                $ont = $ontDeviceId === null
                    ? null
                    : OntDevice::query()->lockForUpdate()->find($ontDeviceId);

                if ($ont === null) {
                    throw ValidationException::withMessages([
                        'ont_device_id' => 'Node ONT wajib dikaitkan dengan satu perangkat ONT.',
                    ]);
                }

                if ($ont->network_node_id !== null) {
                    throw ValidationException::withMessages([
                        'ont_device_id' => 'ONT ini sudah terpasang pada node topologi lain.',
                    ]);
                }
            } elseif ($ontDeviceId !== null) {
                throw ValidationException::withMessages([
                    'ont_device_id' => 'Perangkat ONT hanya dapat dikaitkan pada node bertipe ONT.',
                ]);
            }

            $node = NetworkNode::query()->create([
                'parent_id' => $parent?->id,
                'type' => $type,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'technology' => $data['technology'] ?? 'unknown',
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($ont !== null) {
                $ont->forceFill(['network_node_id' => $node->id])->save();
            }

            if ($parent !== null) {
                NetworkLink::query()->create([
                    'from_node_id' => $parent->id,
                    'to_node_id' => $node->id,
                    'status' => 'unknown',
                ]);
            }

            $this->auditLog->record(
                'network.node.created',
                $node,
                "Node topologi {$node->type} {$node->name} dibuat.",
                ['type' => $node->type, 'parent_id' => $node->parent_id, 'ont_device_id' => $ont?->id]
            );

            return $node;
        });
    }

    /**
     * Ubah atribut node. Kunci `parent_id` hanya diproses bila ada di $data
     * (null berarti lepas dari parent).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateNode(NetworkNode $node, array $data, User $actor): NetworkNode
    {
        return DB::transaction(function () use ($node, $data): NetworkNode {
            $node = NetworkNode::query()->lockForUpdate()->findOrFail($node->id);
            $original = $node->only(['name', 'code', 'technology', 'latitude', 'longitude', 'sort_order', 'is_active']);

            $latitude = array_key_exists('latitude', $data) ? $data['latitude'] : $node->latitude;
            $longitude = array_key_exists('longitude', $data) ? $data['longitude'] : $node->longitude;
            $this->assertCoordinatePair($latitude, $longitude);

            $node->fill(array_intersect_key($data, array_flip([
                'name', 'code', 'technology', 'latitude', 'longitude', 'sort_order', 'is_active',
            ])));

            $reparentedFrom = false;
            $oldParentId = $node->parent_id;

            if (array_key_exists('parent_id', $data) && (int) $data['parent_id'] !== (int) $node->parent_id) {
                $parent = $this->findParent($data['parent_id']);
                $this->assertParentAllowed($node->type, $parent, $node);
                $this->moveUnderParent($node, $parent);
                $reparentedFrom = true;
            }

            $node->save();

            $changes = array_keys(array_diff_assoc(
                $node->only(array_keys($original)),
                $original
            ));

            $movedOnMap = array_intersect($changes, ['latitude', 'longitude']) !== [];

            if ($movedOnMap || $reparentedFrom) {
                $this->recalculateNodeCables($node);
            }

            if ($changes !== []) {
                $this->auditLog->record(
                    'network.node.updated',
                    $node,
                    "Node topologi {$node->name} diperbarui.",
                    ['fields' => $changes]
                );
            }

            if ($reparentedFrom) {
                $this->auditLog->record(
                    'network.node.reparented',
                    $node,
                    "Node topologi {$node->name} dipindahkan ke parent lain.",
                    ['old_parent_id' => $oldParentId, 'new_parent_id' => $node->parent_id]
                );
            }

            return $node;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createPort(NetworkNode $node, array $data, User $actor): NetworkPort
    {
        $exists = NetworkPort::query()
            ->where('network_node_id', $node->id)
            ->where('port_number', $data['port_number'])
            ->where('port_type', $data['port_type'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'port_number' => 'Port dengan nomor dan tipe tersebut sudah ada pada node ini.',
            ]);
        }

        try {
            $port = $node->ports()->create([
                'port_number' => $data['port_number'],
                'port_type' => $data['port_type'],
                'capacity' => $data['capacity'] ?? null,
                'technology' => $data['technology'] ?? $node->technology,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'port_number' => 'Port dengan nomor dan tipe tersebut sudah ada pada node ini.',
            ]);
        }

        $this->auditLog->record(
            'network.port.created',
            $port,
            "Port {$port->port_type} {$port->port_number} ditambahkan pada {$node->name}.",
            ['network_node_id' => $node->id]
        );

        return $port;
    }

    /**
     * Hubungkan node yatim ke parent. Node yang sudah memiliki parent ditolak
     * supaya satu ONT tidak pernah berada pada dua ODP.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createLink(array $data, User $actor): NetworkLink
    {
        return DB::transaction(function () use ($data): NetworkLink {
            $fromId = $data['from_node_id'] ?? null;
            $toId = $data['to_node_id'] ?? null;

            if ($fromId !== null && $toId !== null && (int) $fromId === (int) $toId) {
                throw ValidationException::withMessages([
                    'from_node_id' => 'Node sumber dan tujuan tidak boleh sama.',
                ]);
            }

            $from = $fromId !== null
                ? NetworkNode::query()->lockForUpdate()->find($fromId)
                : null;
            $to = $toId !== null
                ? NetworkNode::query()->lockForUpdate()->find($toId)
                : null;

            if ($fromId !== null && $from === null) {
                throw ValidationException::withMessages([
                    'from_node_id' => 'Node sumber tidak ditemukan.',
                ]);
            }

            if ($toId !== null && $to === null) {
                throw ValidationException::withMessages([
                    'to_node_id' => 'Node tujuan tidak ditemukan.',
                ]);
            }

            if ($from !== null && $to !== null) {
                $this->assertParentAllowed($to->type, $from, $to);
            }

            $this->assertPorts(null, $from?->id, $to?->id, $data['from_port_id'] ?? null, $data['to_port_id'] ?? null);

            $waypoints = $this->normalizePath($data['path_coordinates'] ?? null);

            $link = NetworkLink::query()->create([
                'from_node_id' => $from?->id,
                'from_port_id' => $data['from_port_id'] ?? null,
                'to_node_id' => $to?->id,
                'to_port_id' => $data['to_port_id'] ?? null,
                'path_coordinates' => $waypoints,
                'cable_length_m' => $this->measureCable($from, $to, $waypoints),
                'measured_attenuation_db' => $data['measured_attenuation_db'] ?? null,
                'calculated_attenuation_db' => $data['calculated_attenuation_db'] ?? null,
                'status' => $data['status'] ?? 'unknown',
                'cable_type' => $data['cable_type'] ?? null,
            ]);

            if ($to !== null && $from !== null && $to->parent_id === null) {
                $to->forceFill(['parent_id' => $from->id])->save();
            }

            $this->auditLog->record(
                'network.link.created',
                $link,
                'Link '.($from?->name ?? 'bebas').' → '.($to?->name ?? 'bebas').' dibuat.',
                ['from_node_id' => $from?->id, 'to_node_id' => $to?->id]
            );

            return $link;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateLink(NetworkLink $link, array $data, User $actor): NetworkLink
    {
        return DB::transaction(function () use ($link, $data): NetworkLink {
            $link = NetworkLink::query()->lockForUpdate()->findOrFail($link->id);

            $fromPortId = array_key_exists('from_port_id', $data) ? $data['from_port_id'] : $link->from_port_id;
            $toPortId = array_key_exists('to_port_id', $data) ? $data['to_port_id'] : $link->to_port_id;
            $this->assertPorts($link, $link->from_node_id, $link->to_node_id, $fromPortId, $toPortId);

            $link->fill(array_intersect_key($data, array_flip([
                'from_port_id', 'to_port_id',
                'measured_attenuation_db', 'calculated_attenuation_db', 'status', 'cable_type',
            ])));

            if (array_key_exists('path_coordinates', $data)) {
                $link->path_coordinates = $this->normalizePath($data['path_coordinates']);
            }

            $link->cable_length_m = $this->measureCable(
                $link->fromNode,
                $link->toNode,
                $link->path_coordinates,
            );

            $link->save();

            $this->auditLog->record(
                'network.link.updated',
                $link,
                "Link topologi #{$link->id} diperbarui.",
                ['fields' => array_keys($data)]
            );

            return $link;
        });
    }

    /**
     * Rantai dari root sampai node itu sendiri.
     *
     * @return array<int, array{id: int, type: string, name: string}>
     */
    public function pathTo(NetworkNode $node): array
    {
        $path = [];
        $visited = [];
        $current = $node;

        while ($current !== null && ! isset($visited[$current->id])) {
            $visited[$current->id] = true;
            array_unshift($path, ['id' => $current->id, 'type' => $current->type, 'name' => $current->name]);
            $current = $current->parent_id === null
                ? null
                : NetworkNode::query()->find($current->parent_id, ['id', 'parent_id', 'type', 'name']);
        }

        return $path;
    }

    /**
     * @return array{
     *     nodes: array<int, array<string, mixed>>,
     *     links: array<int, array<string, mixed>>,
     *     tree: array<int, array<string, mixed>>,
     *     allowedParents: array<string, array<int, string>>,
     *     stats: array<string, int>
     * }
     */
    public function buildGraph(): array
    {
        $nodes = NetworkNode::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $links = NetworkLink::query()->get();
        $ports = NetworkPort::query()
            ->orderBy('port_number')
            ->get(['id', 'network_node_id', 'port_number', 'port_type', 'capacity'])
            ->groupBy('network_node_id');
        $onts = OntDevice::query()
            ->whereNotNull('network_node_id')
            ->with('customer:id,customer_code,name,latitude,longitude')
            ->get([
                'id', 'customer_id', 'network_node_id', 'serial_number',
                'last_inform_at', 'inform_interval_seconds', 'connection_status',
                'rx_power_dbm', 'rx_power_quality',
            ])
            ->keyBy('network_node_id');

        $children = $nodes->groupBy('parent_id');
        $ontInfo = $onts->map(fn (OntDevice $ont): array => $this->ontInfo($ont));
        $summaries = [];

        $summarize = function (int $nodeId) use (&$summarize, &$summaries, $children, $ontInfo): array {
            if (isset($summaries[$nodeId])) {
                return $summaries[$nodeId];
            }

            $summaries[$nodeId] = $this->emptySummary();
            $summary = $this->emptySummary();

            if ($ontInfo->has($nodeId)) {
                $summary = $this->addToSummary($summary, $ontInfo[$nodeId]['status'], $ontInfo[$nodeId]['quality']);
            }

            foreach ($children->get($nodeId, collect()) as $child) {
                $childSummary = $summarize($child->id);
                foreach (['ont_total', 'online', 'warning', 'offline', 'unknown'] as $key) {
                    $summary[$key] += $childSummary[$key];
                }
                $summary['worst_severity'] = max($summary['worst_severity'], $childSummary['worst_severity']);
            }

            return $summaries[$nodeId] = $summary;
        };

        foreach ($nodes as $node) {
            $summarize($node->id);
        }

        $nodeRows = $nodes->map(function (NetworkNode $node) use ($ports, $ontInfo, $summaries): array {
            $ont = $ontInfo->get($node->id);
            $latitude = $node->latitude ?? ($ont['latitude'] ?? null);
            $longitude = $node->longitude ?? ($ont['longitude'] ?? null);

            return [
                'id' => $node->id,
                'parent_id' => $node->parent_id,
                'type' => $node->type,
                'name' => $node->name,
                'code' => $node->code,
                'technology' => $node->technology,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'own_coordinates' => $node->latitude !== null && $node->longitude !== null,
                'sort_order' => $node->sort_order,
                'is_active' => $node->is_active,
                'ports' => $ports->get($node->id, collect())->map(fn (NetworkPort $port): array => [
                    'id' => $port->id,
                    'port_number' => $port->port_number,
                    'port_type' => $port->port_type,
                    'capacity' => $port->capacity,
                ])->values()->all(),
                'ont' => $ont,
                'summary' => $this->publicSummary($summaries[$node->id]),
            ];
        })->values();

        $linkRows = $links->map(function (NetworkLink $link) use ($summaries): array {
            $summary = $this->publicSummary($summaries[$link->to_node_id] ?? $this->emptySummary());

            return [
                'id' => $link->id,
                'from_node_id' => $link->from_node_id,
                'from_port_id' => $link->from_port_id,
                'to_node_id' => $link->to_node_id,
                'to_port_id' => $link->to_port_id,
                'path' => $link->path_coordinates ?? [],
                'cable_length_m' => $link->cable_length_m,
                'measured_attenuation_db' => $link->measured_attenuation_db,
                'calculated_attenuation_db' => $link->calculated_attenuation_db,
                'status' => $link->status,
                'cable_type' => $link->cable_type,
                'quality' => $summary['quality'],
                'acs_status' => $summary['status'],
                'summary' => $summary,
            ];
        })->values();

        return [
            'nodes' => $nodeRows->all(),
            'links' => $linkRows->all(),
            'tree' => $this->buildTree($nodeRows),
            'allowedParents' => self::ALLOWED_PARENTS,
            'stats' => [
                'nodes' => $nodes->count(),
                'links' => $links->count(),
                'ont_nodes' => $onts->count(),
                'orphans' => $nodes->filter(
                    fn (NetworkNode $node): bool => $node->parent_id === null && $node->type !== 'server'
                )->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $nodeRows
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $nodeRows): array
    {
        $byParent = $nodeRows->groupBy(fn (array $row): string => (string) ($row['parent_id'] ?? 'root'));
        $visited = [];

        $build = function (array $row) use (&$build, $byParent, &$visited): array {
            $visited[$row['id']] = true;
            $childRows = $byParent->get((string) $row['id'], collect())
                ->reject(fn (array $child): bool => isset($visited[$child['id']]));

            return [
                'id' => $row['id'],
                'type' => $row['type'],
                'name' => $row['name'],
                'code' => $row['code'],
                'is_active' => $row['is_active'],
                'ont' => $row['ont'] === null ? null : [
                    'status' => $row['ont']['status'],
                    'quality' => $row['ont']['quality'],
                ],
                'summary' => $row['summary'],
                'children' => $childRows->map(fn (array $child): array => $build($child))->values()->all(),
            ];
        };

        return $byParent->get('root', collect())
            ->map(fn (array $row): array => $build($row))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function ontInfo(OntDevice $ont): array
    {
        $status = $ont->connection_status === 'missing'
            ? 'missing'
            : $this->statusService->calculate($ont->last_inform_at, $ont->inform_interval_seconds);

        return [
            'ont_device_id' => $ont->id,
            'serial_number' => $ont->serial_number,
            'customer_name' => $ont->customer?->name,
            'customer_code' => $ont->customer?->customer_code,
            'latitude' => $ont->customer?->latitude,
            'longitude' => $ont->customer?->longitude,
            'rx_power_dbm' => $ont->rx_power_dbm === null ? null : (float) $ont->rx_power_dbm,
            'quality' => $ont->rx_power_quality ?: 'unknown',
            'status' => $status,
            'last_inform_at' => $ont->last_inform_at?->toIso8601String(),
            'last_inform_human' => $ont->last_inform_at?->diffForHumans(),
            'url' => route('ont-devices.show', $ont),
        ];
    }

    /**
     * @return array{ont_total: int, online: int, warning: int, offline: int, unknown: int, worst_severity: int}
     */
    private function emptySummary(): array
    {
        return ['ont_total' => 0, 'online' => 0, 'warning' => 0, 'offline' => 0, 'unknown' => 0, 'worst_severity' => 0];
    }

    /**
     * @param  array{ont_total: int, online: int, warning: int, offline: int, unknown: int, worst_severity: int}  $summary
     * @return array{ont_total: int, online: int, warning: int, offline: int, unknown: int, worst_severity: int}
     */
    private function addToSummary(array $summary, string $status, string $quality): array
    {
        $summary['ont_total']++;
        $bucket = match ($status) {
            'online' => 'online',
            'warning' => 'warning',
            'offline', 'missing' => 'offline',
            default => 'unknown',
        };
        $summary[$bucket]++;
        $summary['worst_severity'] = max(
            $summary['worst_severity'],
            self::QUALITY_SEVERITY[$quality] ?? 0
        );

        return $summary;
    }

    /**
     * Kabel hulu tetap "online" bila ada ONT hilir yang online; offline hanya
     * bila seluruh ONT hilir offline.
     *
     * @param  array{ont_total: int, online: int, warning: int, offline: int, unknown: int, worst_severity: int}  $summary
     * @return array<string, mixed>
     */
    private function publicSummary(array $summary): array
    {
        $status = match (true) {
            $summary['online'] > 0 => 'online',
            $summary['warning'] > 0 => 'warning',
            $summary['offline'] > 0 => 'offline',
            default => 'unknown',
        };

        return [
            'ont_total' => $summary['ont_total'],
            'online' => $summary['online'],
            'warning' => $summary['warning'],
            'offline' => $summary['offline'],
            'unknown' => $summary['unknown'],
            'status' => $status,
            'quality' => (string) array_search($summary['worst_severity'], self::QUALITY_SEVERITY, true),
        ];
    }

    private function findParent(mixed $parentId): ?NetworkNode
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        $parent = NetworkNode::query()->lockForUpdate()->find($parentId);

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent tidak ditemukan.',
            ]);
        }

        return $parent;
    }

    /**
     * @throws ValidationException
     */
    private function assertParentAllowed(string $type, ?NetworkNode $parent, ?NetworkNode $self): void
    {
        $allowed = self::ALLOWED_PARENTS[$type] ?? null;

        if ($allowed === null) {
            throw ValidationException::withMessages(['type' => 'Tipe node tidak dikenal.']);
        }

        if ($parent === null) {
            return;
        }

        if ($allowed === []) {
            throw ValidationException::withMessages([
                'parent_id' => 'Node Server adalah akar dan tidak boleh memiliki parent.',
            ]);
        }

        if ($self !== null && $parent->id === $self->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Node tidak boleh menjadi parent bagi dirinya sendiri.',
            ]);
        }

        if (! in_array($parent->type, $allowed, true)) {
            throw ValidationException::withMessages([
                'parent_id' => "Parent bertipe {$parent->type} tidak valid untuk node {$type}. Yang diperbolehkan: ".implode(', ', $allowed).'.',
            ]);
        }

        if ($self !== null && $this->wouldCreateCycle($self, $parent)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Pemindahan ini membentuk siklus pada topologi.',
            ]);
        }
    }

    private function wouldCreateCycle(NetworkNode $self, NetworkNode $newParent): bool
    {
        $visited = [];
        $currentId = $newParent->id;

        while ($currentId !== null) {
            if ($currentId === $self->id) {
                return true;
            }

            if (isset($visited[$currentId])) {
                return true;
            }

            $visited[$currentId] = true;
            $currentId = NetworkNode::query()->whereKey($currentId)->value('parent_id');
        }

        return false;
    }

    private function moveUnderParent(NetworkNode $node, ?NetworkNode $parent): void
    {
        $link = NetworkLink::query()->where('to_node_id', $node->id)->first();

        if ($parent === null) {
            $link?->delete();
            $node->parent_id = null;

            return;
        }

        if ($link !== null) {
            $link->forceFill([
                'from_node_id' => $parent->id,
                'from_port_id' => null,
                'path_coordinates' => null,
            ])->save();
        } else {
            NetworkLink::query()->create([
                'from_node_id' => $parent->id,
                'to_node_id' => $node->id,
                'status' => 'unknown',
            ]);
        }

        $node->parent_id = $parent->id;
    }

    /**
     * @throws ValidationException
     */
    private function assertCoordinatePair(mixed $latitude, mixed $longitude): void
    {
        if (($latitude === null) !== ($longitude === null)) {
            throw ValidationException::withMessages([
                'latitude' => 'Latitude dan longitude harus diisi bersamaan.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertPorts(
        ?NetworkLink $link,
        ?int $fromNodeId,
        ?int $toNodeId,
        mixed $fromPortId,
        mixed $toPortId,
    ): void {
        foreach ([['from_port_id', $fromPortId, $fromNodeId], ['to_port_id', $toPortId, $toNodeId]] as [$field, $portId, $nodeId]) {
            if ($portId === null || $portId === '') {
                continue;
            }

            $port = NetworkPort::query()->find($portId);

            if ($port === null || (int) $port->network_node_id !== (int) $nodeId) {
                throw ValidationException::withMessages([
                    $field => 'Port harus milik node pada sisi link tersebut.',
                ]);
            }

            $inUse = NetworkLink::query()
                ->when($link !== null, fn ($query) => $query->whereKeyNot($link->id))
                ->where(function ($query) use ($portId): void {
                    $query->where('from_port_id', $portId)->orWhere('to_port_id', $portId);
                })
                ->exists();

            if ($inUse) {
                throw ValidationException::withMessages([
                    $field => 'Port ini sudah dipakai oleh link lain.',
                ]);
            }
        }
    }

    /**
     * Panjang kabel dihitung otomatis dari geometri jalur pada peta:
     * koordinat node asal + waypoint + koordinat node tujuan.
     * Tidak ada input manual; angka selalu mengikuti posisi terbaru di peta.
     *
     * @param  array<int, array{0: float, 1: float}>|null  $waypoints
     */
    private function measureCable(
        ?NetworkNode $from,
        ?NetworkNode $to,
        ?array $waypoints,
    ): ?float {
        $points = $waypoints ?? [];
        $fromPoint = $this->nodePoint($from);
        $toPoint = $this->nodePoint($to);

        if ($fromPoint !== null) {
            array_unshift($points, $fromPoint);
        }

        if ($toPoint !== null) {
            $points[] = $toPoint;
        }

        if (count($points) < 2) {
            return null;
        }

        $meters = 0.0;
        $previous = $points[0];

        foreach (array_slice($points, 1) as $point) {
            $meters += $this->haversineMeters($previous, $point);
            $previous = $point;
        }

        return round($meters, 2);
    }

    /**
     * Hitung ulang panjang semua kabel yang menyentuh node, dipakai setelah
     * node digeser atau dipindahkan agar angka selalu sesuai peta.
     */
    private function recalculateNodeCables(NetworkNode $node): void
    {
        NetworkLink::query()
            ->where('from_node_id', $node->id)
            ->orWhere('to_node_id', $node->id)
            ->get()
            ->each(function (NetworkLink $link) use ($node): void {
                $link->cable_length_m = $this->measureCable(
                    $node,
                    $link->to_node_id === $node->id ? $link->fromNode : $node,
                    $link->path_coordinates,
                );
                $link->saveQuietly();
            });
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function nodePoint(?NetworkNode $node): ?array
    {
        if ($node === null || $node->latitude === null || $node->longitude === null) {
            return null;
        }

        return [(float) $node->latitude, (float) $node->longitude];
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private function haversineMeters(array $from, array $to): float
    {
        [$lat1, $lng1] = $from;
        [$lat2, $lng2] = $to;

        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return array<int, array{0: float, 1: float}>|null
     *
     * @throws ValidationException
     */
    private function normalizePath(mixed $path): ?array
    {
        if ($path === null || $path === []) {
            return null;
        }

        if (! is_array($path) || ! array_is_list($path) || count($path) > self::MAX_WAYPOINTS) {
            throw ValidationException::withMessages([
                'path_coordinates' => 'Waypoint harus berupa daftar maksimal '.self::MAX_WAYPOINTS.' titik [lat, lng].',
            ]);
        }

        $normalized = [];

        foreach ($path as $point) {
            if (
                ! is_array($point)
                || count($point) !== 2
                || ! is_numeric($point[0] ?? null)
                || ! is_numeric($point[1] ?? null)
                || abs((float) $point[0]) > 90
                || abs((float) $point[1]) > 180
            ) {
                throw ValidationException::withMessages([
                    'path_coordinates' => 'Setiap waypoint harus [latitude, longitude] yang valid.',
                ]);
            }

            $normalized[] = [round((float) $point[0], 7), round((float) $point[1], 7)];
        }

        return $normalized;
    }
}
