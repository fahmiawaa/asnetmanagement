<?php

namespace App\Http\Controllers;

use App\Models\NetworkLink;
use App\Models\NetworkNode;
use App\Models\NetworkPort;
use App\Models\OntDevice;
use App\Services\NetworkTopologyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NetworkTopologyController extends Controller
{
    public function index(Request $request, NetworkTopologyService $topology): View
    {
        Gate::authorize('viewAny', NetworkNode::class);

        $validated = $request->validate([
            'focus' => ['nullable', 'integer'],
        ]);
        $canManage = Gate::allows('manage', NetworkNode::class);

        return view('network.topology', [
            'graph' => $topology->buildGraph(),
            'focusId' => isset($validated['focus']) ? (int) $validated['focus'] : null,
            'canManage' => $canManage,
            'ontOptions' => $canManage
                ? OntDevice::query()
                    ->whereNull('network_node_id')
                    ->with('customer:id,name')
                    ->orderBy('serial_number')
                    ->limit(500)
                    ->get(['id', 'customer_id', 'serial_number', 'genieacs_device_id'])
                    ->map(fn (OntDevice $ont): array => [
                        'id' => $ont->id,
                        'label' => ($ont->serial_number ?? $ont->genieacs_device_id)
                            .($ont->customer ? ' — '.$ont->customer->name : ''),
                    ])
                    ->values()
                    ->all()
                : [],
            'nodeTypes' => NetworkNode::TYPES,
            'technologies' => NetworkNode::TECHNOLOGIES,
            'portTypes' => NetworkPort::TYPES,
            'linkStatuses' => NetworkLink::STATUSES,
            'cableTypes' => NetworkLink::CABLE_TYPES,
        ]);
    }

    public function storeNode(Request $request, NetworkTopologyService $topology): RedirectResponse
    {
        Gate::authorize('manage', NetworkNode::class);

        $validated = $request->validate([
            'type' => ['required', Rule::in(NetworkNode::TYPES)],
            'parent_id' => ['nullable', 'integer', 'exists:network_nodes,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'technology' => ['nullable', Rule::in(NetworkNode::TECHNOLOGIES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'ont_device_id' => ['nullable', 'integer', 'exists:ont_devices,id'],
        ]);

        $node = $topology->createNode($validated, $request->user());

        return redirect()
            ->route('network.topology', ['focus' => $node->id])
            ->with('success', 'Node topologi berhasil dibuat.');
    }

    public function updateNode(
        Request $request,
        NetworkNode $node,
        NetworkTopologyService $topology,
    ): RedirectResponse {
        Gate::authorize('manage', NetworkNode::class);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:network_nodes,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'technology' => ['sometimes', Rule::in(NetworkNode::TECHNOLOGIES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_active', $validated)) {
            $validated['is_active'] = (bool) $validated['is_active'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        }

        $topology->updateNode($node, $validated, $request->user());

        return redirect()
            ->route('network.topology', ['focus' => $node->id])
            ->with('success', 'Node topologi berhasil diperbarui.');
    }

    public function storePort(
        Request $request,
        NetworkNode $node,
        NetworkTopologyService $topology,
    ): RedirectResponse {
        Gate::authorize('manage', NetworkNode::class);

        $validated = $request->validate([
            'port_number' => ['required', 'integer', 'min:1', 'max:999'],
            'port_type' => ['required', Rule::in(NetworkPort::TYPES)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1024'],
            'technology' => ['nullable', Rule::in(NetworkNode::TECHNOLOGIES)],
        ]);

        $topology->createPort($node, $validated, $request->user());

        return redirect()
            ->route('network.topology', ['focus' => $node->id])
            ->with('success', 'Port berhasil ditambahkan.');
    }

    public function storeLink(Request $request, NetworkTopologyService $topology): RedirectResponse
    {
        Gate::authorize('manage', NetworkNode::class);

        $validated = $request->validate([
            'from_node_id' => ['nullable', 'integer', 'exists:network_nodes,id'],
            'to_node_id' => ['nullable', 'integer', 'exists:network_nodes,id'],
            ...$this->linkRules(),
        ]);
        $validated = $this->decodePath($validated);

        $link = $topology->createLink($validated, $request->user());

        return redirect()
            ->route('network.topology', ['focus' => $link->to_node_id ?? $link->from_node_id])
            ->with('success', 'Link topologi berhasil dibuat.');
    }

    public function updateLink(
        Request $request,
        NetworkLink $link,
        NetworkTopologyService $topology,
    ): RedirectResponse {
        Gate::authorize('manage', NetworkNode::class);

        $validated = $request->validate($this->linkRules());
        $validated = $this->decodePath($validated);

        $topology->updateLink($link, $validated, $request->user());

        return redirect()
            ->route('network.topology', ['focus' => $link->to_node_id])
            ->with('success', 'Link topologi berhasil diperbarui.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function linkRules(): array
    {
        return [
            'from_port_id' => ['nullable', 'integer', 'exists:network_ports,id'],
            'to_port_id' => ['nullable', 'integer', 'exists:network_ports,id'],
            'path_coordinates' => ['nullable', 'json'],
            'measured_attenuation_db' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'calculated_attenuation_db' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::in(NetworkLink::STATUSES)],
            'cable_type' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function decodePath(array $validated): array
    {
        if (! array_key_exists('path_coordinates', $validated)) {
            return $validated;
        }

        $raw = $validated['path_coordinates'];
        $validated['path_coordinates'] = $raw === null ? null : json_decode((string) $raw, true);

        if ($validated['path_coordinates'] !== null && ! is_array($validated['path_coordinates'])) {
            throw ValidationException::withMessages([
                'path_coordinates' => 'Waypoint harus berupa daftar [latitude, longitude].',
            ]);
        }

        return $validated;
    }
}
