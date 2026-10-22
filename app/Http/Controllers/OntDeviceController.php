<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeviceTask;
use App\Models\GenieacsServer;
use App\Models\NetworkNode;
use App\Models\OntDevice;
use App\Models\OntParameterProfile;
use App\Services\NetworkTopologyService;
use App\Services\OntMappingService;
use App\Services\OntStatusService;
use App\Services\RxPowerNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OntDeviceController extends Controller
{
    /**
     * Allowlist kolom sorting: kunci dari request => kolom database.
     * Nama kolom tidak pernah diambil langsung dari input pengguna.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'serial' => 'serial_number',
        'customer' => 'customer',
        'manufacturer' => 'manufacturer',
        'product_class' => 'product_class',
        'pon_mode' => 'pon_mode',
        'rx_power' => 'rx_power_dbm',
        'status' => 'connection_status',
        'last_inform' => 'last_inform_at',
    ];

    private const STATUSES = ['online', 'warning', 'offline', 'unknown', 'missing'];

    private const QUALITIES = ['overload', 'good', 'warning', 'critical', 'unknown'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', OntDevice::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'quality' => ['nullable', Rule::in(self::QUALITIES)],
            'pon_mode' => ['nullable', Rule::in(['gpon', 'epon', 'unknown'])],
            'mapping' => ['nullable', Rule::in(['mapped', 'unmapped'])],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTABLE))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $status = $validated['status'] ?? '';
        $quality = $validated['quality'] ?? '';
        $ponMode = $validated['pon_mode'] ?? '';
        $mapping = $validated['mapping'] ?? '';
        $sort = $validated['sort'] ?? 'last_inform';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = OntDevice::query()
            ->with(['customer:id,customer_code,name,pppoe_account_id', 'customer.pppoeAccount:id,username'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = "%{$search}%";

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('genieacs_device_id', 'like', $like)
                        ->orWhere('serial_number', 'like', $like)
                        ->orWhere('pon_mac', 'like', $like)
                        ->orWhere('ip_address', 'like', $like)
                        ->orWhere('pppoe_ip', 'like', $like)
                        ->orWhere('manufacturer', 'like', $like)
                        ->orWhere('product_class', 'like', $like)
                        ->orWhereHas('customer', function (Builder $customer) use ($like): void {
                            $customer->where(function (Builder $customer) use ($like): void {
                                $customer->where('customer_code', 'like', $like)
                                    ->orWhere('name', 'like', $like)
                                    ->orWhereHas('pppoeAccount', function (Builder $account) use ($like): void {
                                        $account->where('username', 'like', $like);
                                    });
                            });
                        });
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('connection_status', $status))
            ->when($quality !== '', fn (Builder $query) => $query->where('rx_power_quality', $quality))
            ->when($ponMode !== '', fn (Builder $query) => $query->where('pon_mode', $ponMode))
            ->when($mapping === 'mapped', fn (Builder $query) => $query->whereNotNull('customer_id'))
            ->when($mapping === 'unmapped', fn (Builder $query) => $query->whereNull('customer_id'));

        $this->applySorting($query, $sort, $direction);

        $ontDevices = $query
            ->paginate(max(1, (int) config('genieacs.ont_list_per_page')))
            ->withQueryString();

        $servers = GenieacsServer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'connection_status', 'last_sync_at']);
        $staleCutoff = now()->subMinutes(max(1, (int) config('genieacs.sync_max_age_minutes')));
        $syncStale = $servers->isEmpty() || $servers->contains(
            fn (GenieacsServer $server): bool => $server->last_sync_at === null
                || $server->last_sync_at->lt($staleCutoff)
        );

        return view('ont-devices.index', [
            'ontDevices' => $ontDevices,
            'stats' => $this->stats(),
            'servers' => $servers,
            'syncStale' => $syncStale,
            'search' => $search,
            'status' => $status,
            'quality' => $quality,
            'ponMode' => $ponMode,
            'mapping' => $mapping,
            'sort' => $sort,
            'direction' => $direction,
            'statusLabels' => $this->statusLabels(),
            'qualityLabels' => $this->qualityLabels(),
        ]);
    }

    public function show(
        OntDevice $ontDevice,
        OntMappingService $mappingService,
        OntStatusService $statusService,
        RxPowerNormalizer $rxNormalizer,
        NetworkTopologyService $topology,
    ): View {
        Gate::authorize('view', $ontDevice);

        $ontDevice->load([
            'customer:id,customer_code,name,pppoe_account_id',
            'customer.pppoeAccount:id,username',
            'genieacsServer:id,name,connection_status',
            'mappedBy:id,name',
        ]);

        $liveStatus = $ontDevice->connection_status === 'missing'
            ? 'missing'
            : $statusService->calculate($ontDevice->last_inform_at, $ontDevice->inform_interval_seconds);
        $interval = $ontDevice->inform_interval_seconds
            ?? (int) config('genieacs.fallback_inform_interval_seconds');
        $canManageMapping = Gate::allows('manageMapping', $ontDevice);
        $unmapped = $ontDevice->customer_id === null;
        $topologyNode = $ontDevice->network_node_id === null
            ? null
            : NetworkNode::query()->find($ontDevice->network_node_id, ['id', 'parent_id', 'type', 'name']);
        $topologyPath = $topologyNode === null ? [] : $topology->pathTo($topologyNode);

        return view('ont-devices.show', [
            'ontDevice' => $ontDevice,
            'liveStatus' => $liveStatus,
            'onlineWithinSeconds' => (2 * $interval) + 60,
            'warningWithinSeconds' => (4 * $interval) + 60,
            'rxThresholds' => $rxNormalizer->thresholdsFor($ontDevice->pon_mode),
            'topologyPath' => $topologyPath,
            'histories' => $ontDevice->statusHistories()
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
            'candidates' => $unmapped && $canManageMapping
                ? $mappingService->candidates($ontDevice)
                : collect(),
            'assignableCustomers' => $unmapped && $canManageMapping
                ? Customer::query()
                    ->whereDoesntHave('ontDevice')
                    ->orderBy('name')
                    ->limit(500)
                    ->get(['id', 'customer_code', 'name'])
                : collect(),
            'canManageMapping' => $canManageMapping,
            'recentTasks' => $ontDevice->tasks()
                ->with('requestedBy:id,name,role')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'openTaskTypes' => $ontDevice->tasks()
                ->withStatuses(DeviceTask::OPEN_STATUSES)
                ->pluck('type')
                ->unique()
                ->values()
                ->all(),
            'profiles' => OntParameterProfile::query()
                ->where('is_verified', true)
                ->matching($ontDevice)
                ->orderBy('name')
                ->get(['id', 'name', 'product_class', 'pon_mode']),
            'tasksEnabled' => (bool) config('genieacs.tasks.enabled'),
            'canRunTask' => Gate::allows('runTask', $ontDevice),
            'taskTypeLabels' => [
                'summon' => 'Summon',
                'reboot' => 'Reboot',
                'set_wifi' => 'Ubah WiFi',
            ],
            'taskStatusLabels' => [
                'pending' => 'Pending',
                'processing' => 'Processing',
                'accepted' => 'Accepted',
                'verified' => 'Verified',
                'failed' => 'Failed',
                'expired' => 'Expired',
                'cancelled' => 'Cancelled',
            ],
            'statusLabels' => $this->statusLabels(),
            'qualityLabels' => $this->qualityLabels(),
        ]);
    }

    public function updateMapping(
        Request $request,
        OntDevice $ontDevice,
        OntMappingService $mappingService,
    ): RedirectResponse {
        Gate::authorize('manageMapping', $ontDevice);

        $validated = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $customer = Customer::query()->findOrFail($validated['customer_id']);
        $mappingService->map($ontDevice, $customer, $request->user());

        return redirect()
            ->route('ont-devices.show', $ontDevice)
            ->with('success', 'ONT berhasil dipetakan ke pelanggan.');
    }

    public function destroyMapping(
        Request $request,
        OntDevice $ontDevice,
        OntMappingService $mappingService,
    ): RedirectResponse {
        Gate::authorize('manageMapping', $ontDevice);

        $mappingService->unmap($ontDevice, $request->user());

        return redirect()
            ->route('ont-devices.show', $ontDevice)
            ->with('success', 'Mapping ONT dilepas dari pelanggan.');
    }

    private function applySorting(Builder $query, string $sort, string $direction): void
    {
        $column = self::SORTABLE[$sort];

        match ($sort) {
            'customer' => $query
                ->orderByRaw('ont_devices.customer_id IS NULL')
                ->orderBy(
                    Customer::query()
                        ->select('name')
                        ->whereColumn('customers.id', 'ont_devices.customer_id')
                        ->limit(1),
                    $direction
                ),
            'status' => $query->orderByRaw(
                "CASE ont_devices.connection_status
                    WHEN 'online' THEN 1
                    WHEN 'warning' THEN 2
                    WHEN 'offline' THEN 3
                    WHEN 'missing' THEN 4
                    ELSE 5 END {$direction}"
            ),
            default => $query
                ->orderByRaw("ont_devices.{$column} IS NULL")
                ->orderBy("ont_devices.{$column}", $direction),
        };

        $query->orderBy('ont_devices.id');
    }

    /**
     * @return array{total: int, online: int, warning: int, offline: int, unmapped: int, critical: int, mapped_percent: int}
     */
    private function stats(): array
    {
        $row = OntDevice::query()
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN connection_status = 'online' THEN 1 ELSE 0 END) AS online,
                SUM(CASE WHEN connection_status = 'warning' THEN 1 ELSE 0 END) AS warning,
                SUM(CASE WHEN connection_status = 'offline' THEN 1 ELSE 0 END) AS offline,
                SUM(CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END) AS unmapped,
                SUM(CASE WHEN rx_power_quality = 'critical' THEN 1 ELSE 0 END) AS critical
            ")
            ->first();

        $total = (int) ($row->total ?? 0);
        $unmapped = (int) ($row->unmapped ?? 0);

        return [
            'total' => $total,
            'online' => (int) ($row->online ?? 0),
            'warning' => (int) ($row->warning ?? 0),
            'offline' => (int) ($row->offline ?? 0),
            'unmapped' => $unmapped,
            'critical' => (int) ($row->critical ?? 0),
            'mapped_percent' => $total > 0 ? (int) round((($total - $unmapped) / $total) * 100) : 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        return [
            'online' => 'Online',
            'warning' => 'Warning',
            'offline' => 'Offline',
            'unknown' => 'Unknown',
            'missing' => 'Missing',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function qualityLabels(): array
    {
        return [
            'overload' => 'Overload',
            'good' => 'Baik',
            'warning' => 'Warning',
            'critical' => 'Kritis',
            'unknown' => 'Unknown',
        ];
    }
}
