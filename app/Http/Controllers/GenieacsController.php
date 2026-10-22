<?php

namespace App\Http\Controllers;

use App\Models\GenieacsServer;
use App\Services\AuditLogService;
use App\Services\GenieAcsService;
use App\Services\OntSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Throwable;

class GenieacsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewGenieacs', GenieacsServer::class);

        $servers = GenieacsServer::query()
            ->withCount('ontDevices')
            ->orderBy('name')
            ->get();

        $staleCutoff = now()->subMinutes(max(1, (int) config('genieacs.sync_max_age_minutes')));

        return view('genieacs.index', [
            'servers' => $servers,
            'syncStale' => $servers->isEmpty() || $servers->contains(
                fn (GenieacsServer $server): bool => $server->last_sync_at === null
                    || $server->last_sync_at->lt($staleCutoff)
            ),
            'ontTotal' => $servers->sum('ont_devices_count'),
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('manageGenieacs', GenieacsServer::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'base_url' => ['required', 'string', 'max:255', 'url:http,https'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $baseUrl = rtrim($validated['base_url'], '/');
        $existing = GenieacsServer::query()
            ->where('base_url', $baseUrl)
            ->first();

        $server = $existing ?? new GenieacsServer;
        $isNew = ! $server->exists;

        $server->name = $validated['name'];
        $server->base_url = $baseUrl;
        $server->username = $validated['username'] ?? null;
        $server->is_active = (bool) ($validated['is_active'] ?? true);

        if (array_key_exists('password', $validated) && filled($validated['password'])) {
            $server->encrypted_password = $validated['password'];
        } elseif (! $isNew && $validated['username'] === null) {
            $server->encrypted_password = null;
        }

        $server->save();

        $audit->record(
            $isNew ? 'genieacs.server.created' : 'genieacs.server.updated',
            $server,
            ($isNew ? 'Server GenieACS dibuat: ' : 'Server GenieACS diperbarui: ').$server->name,
            ['base_url' => $server->base_url, 'is_active' => $server->is_active],
        );

        return redirect()
            ->route('genieacs.index')
            ->with('success', 'Konfigurasi server GenieACS tersimpan.');
    }

    public function test(
        Request $request,
        GenieAcsService $genieAcs,
        AuditLogService $audit,
    ): RedirectResponse {
        Gate::authorize('manageGenieacs', GenieacsServer::class);

        $validated = $request->validate([
            'server_id' => ['required', 'integer', Rule::exists('genieacs_servers', 'id')],
        ]);

        $server = GenieacsServer::query()->findOrFail($validated['server_id']);
        $connected = $genieAcs->testConnection($server);

        $audit->record(
            'genieacs.connection.tested',
            $server,
            'Uji koneksi NBI GenieACS: '.($connected ? 'berhasil' : 'gagal'),
            ['base_url' => $server->base_url, 'connected' => $connected],
        );

        return redirect()
            ->route('genieacs.index')
            ->with($connected ? 'success' : 'error', $connected
                ? "Koneksi ke {$server->name} berhasil."
                : "Koneksi ke {$server->name} gagal. Periksa URL, jaringan internal, dan autentikasi.");
    }

    public function sync(
        Request $request,
        OntSyncService $syncService,
        AuditLogService $audit,
    ): RedirectResponse {
        Gate::authorize('manageGenieacs', GenieacsServer::class);

        $validated = $request->validate([
            'server_id' => ['nullable', 'integer', Rule::exists('genieacs_servers', 'id')],
        ]);

        $query = GenieacsServer::query()->where('is_active', true);

        if (filled($validated['server_id'] ?? null)) {
            $query->whereKey($validated['server_id']);
        }

        $servers = $query->get();

        if ($servers->isEmpty()) {
            return redirect()
                ->route('genieacs.index')
                ->with('error', 'Tidak ada server GenieACS aktif untuk disinkronkan.');
        }

        $processed = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($servers as $server) {
            try {
                $result = $syncService->sync($server);
                $processed += $result['processed'];
                $created += $result['created'];
                $updated += $result['updated'];
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $server->forceFill([
                    'connection_status' => 'failed',
                    'last_error_at' => now(),
                    'last_error_message' => str($exception->getMessage())->limit(1000),
                ])->save();
            }
        }

        $audit->record(
            'genieacs.ont.synced',
            null,
            'Sinkronisasi ONT manual dari panel GenieACS',
            [
                'servers' => $servers->count(),
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
            ],
        );

        $message = "Sinkronisasi selesai: {$processed} diproses, {$created} baru, {$updated} diperbarui.";

        if ($failed > 0) {
            return redirect()
                ->route('genieacs.index')
                ->with('error', $message." {$failed} server gagal dihubungi.");
        }

        return redirect()->route('genieacs.index')->with('success', $message);
    }
}
