<?php

namespace App\Http\Controllers;

use App\Models\MikrotikDevice;
use App\Services\MikrotikConnectionService;
use App\Services\MikrotikSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class MikrotikDeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:unknown,connected,failed,inactive',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $status = $validated['status'] ?? '';

        $devices = MikrotikDevice::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('host', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('connection_status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('mikrotik-devices.index', compact(
            'devices',
            'search',
            'status'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('mikrotik-devices.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'api_port' => 'required|integer|min:1|max:65535',
            'use_ssl' => 'nullable|boolean',
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:1',
            'notes' => 'nullable|string',
        ]);

        MikrotikDevice::create([
            'name' => $validated['name'],
            'host' => $validated['host'],
            'api_port' => $validated['api_port'],
            'use_ssl' => $request->boolean('use_ssl'),
            'username' => $validated['username'],
            'encrypted_password' => Crypt::encryptString($validated['password']),
            'connection_status' => 'unknown',
            'last_sync_at' => null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('mikrotik-devices.index')
            ->with('success', 'Perangkat MikroTik berhasil disimpan.');
    }

    public function testConnection(
        MikrotikDevice $mikrotikDevice,
        MikrotikConnectionService $connectionService,
        MikrotikSyncService $syncService
    ) {
        try {
            $client = $connectionService->connect($mikrotikDevice);

            $client
                ->query('/system/identity/print')
                ->read();

            $result = $syncService->sync($mikrotikDevice);
            $profileTotal =
                $result['profiles']['created'] +
                $result['profiles']['updated'];
            $secretTotal =
                $result['secrets']['created'] +
                $result['secrets']['updated'];
            $sessionTotal = $result['sessions']['active'];

            return redirect()
                ->route('mikrotik-devices.index')
                ->with(
                    'success',
                    "Koneksi berhasil. Import read-only selesai: {$profileTotal} PPP Profile, ".
                    "{$secretTotal} PPP Secret, dan {$sessionTotal} sesi aktif."
                )
                ->with('mikrotik_sync_result', [
                    'device' => $mikrotikDevice->name,
                    'profiles' => $result['profiles'],
                    'secrets' => $result['secrets'],
                    'sessions' => $result['sessions'],
                ]);
        } catch (Throwable $exception) {
            $mikrotikDevice->update([
                'connection_status' => 'failed',
            ]);

            return redirect()
                ->route('mikrotik-devices.index')
                ->with(
                    'error',
                    'Koneksi MikroTik gagal. Periksa host, port API, username, password, dan firewall.'
                );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MikrotikDevice $mikrotikDevice)
    {
        return view(
            'mikrotik-devices.edit',
            compact('mikrotikDevice')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        Request $request,
        MikrotikDevice $mikrotikDevice
    ) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'api_port' => 'required|integer|min:1|max:65535',
            'use_ssl' => 'nullable|boolean',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|min:1',
            'notes' => 'nullable|string',
        ]);

        $useSsl = $request->boolean('use_ssl');

        $connectionChanged =
            $mikrotikDevice->host !== $validated['host']
            || (int) $mikrotikDevice->api_port !== (int) $validated['api_port']
            || (bool) $mikrotikDevice->use_ssl !== $useSsl
            || $mikrotikDevice->username !== $validated['username']
            || $request->filled('password');

        $data = [
            'name' => $validated['name'],
            'host' => $validated['host'],
            'api_port' => $validated['api_port'],
            'use_ssl' => $useSsl,
            'username' => $validated['username'],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($request->filled('password')) {
            $data['encrypted_password'] = Crypt::encryptString(
                $validated['password']
            );
        }

        if ($connectionChanged) {
            $data['connection_status'] = 'unknown';
            $data['last_sync_at'] = null;
        }

        $mikrotikDevice->update($data);

        return redirect()
            ->route('mikrotik-devices.index')
            ->with('success', 'Perangkat MikroTik berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MikrotikDevice $mikrotikDevice)
    {
        $mikrotikDevice->update([
            'connection_status' => 'inactive',
        ]);

        return redirect()
            ->route('mikrotik-devices.index')
            ->with('success', 'Perangkat MikroTik dinonaktifkan tanpa menghapus histori.');
    }
}
