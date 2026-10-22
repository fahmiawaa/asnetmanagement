<?php

namespace App\Http\Controllers;

use App\Models\OntDevice;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OpticalAlertController extends Controller
{
    private const TYPES = ['all', 'offline', 'optical'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', OntDevice::class);

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(self::TYPES)],
        ]);

        $type = $validated['type'] ?? 'all';

        $query = OntDevice::query()
            ->with([
                'customer:id,customer_code,name',
                'networkNode:id,name,type',
            ])
            ->when($type === 'offline', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereIn('connection_status', ['offline', 'missing'])
            ))
            ->when($type === 'optical', fn (Builder $query) => $query->whereIn(
                'rx_power_quality',
                ['critical', 'overload']
            ))
            ->when($type === 'all', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereIn('connection_status', ['offline', 'missing'])
                    ->orWhereIn('rx_power_quality', ['critical', 'overload'])
            ));

        $alerts = $query
            ->orderByRaw("CASE connection_status WHEN 'offline' THEN 1 WHEN 'missing' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE rx_power_quality WHEN 'overload' THEN 1 WHEN 'critical' THEN 2 ELSE 3 END")
            ->orderBy('serial_number')
            ->paginate(max(1, (int) config('genieacs.ont_list_per_page')))
            ->withQueryString();

        return view('optical-alerts.index', [
            'alerts' => $alerts,
            'type' => $type,
            'stats' => $this->stats(),
            'qualityLabels' => $this->qualityLabels(),
            'statusLabels' => $this->statusLabels(),
            'alertsEnabled' => (bool) config('genieacs.alerts.enabled'),
        ]);
    }

    /**
     * @return array{offline: int, missing: int, critical: int, overload: int}
     */
    private function stats(): array
    {
        $row = OntDevice::query()
            ->selectRaw("
                SUM(CASE WHEN connection_status = 'offline' THEN 1 ELSE 0 END) AS offline,
                SUM(CASE WHEN connection_status = 'missing' THEN 1 ELSE 0 END) AS missing,
                SUM(CASE WHEN rx_power_quality = 'critical' THEN 1 ELSE 0 END) AS critical,
                SUM(CASE WHEN rx_power_quality = 'overload' THEN 1 ELSE 0 END) AS overload
            ")
            ->first();

        return [
            'offline' => (int) ($row->offline ?? 0),
            'missing' => (int) ($row->missing ?? 0),
            'critical' => (int) ($row->critical ?? 0),
            'overload' => (int) ($row->overload ?? 0),
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
}
