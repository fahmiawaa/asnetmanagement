<?php

namespace App\Http\Controllers;

use App\Models\MikrotikDevice;
use App\Models\PppoeAccount;
use App\Models\PppoeActiveSession;
use App\Services\AuditLogService;
use App\Services\MikrotikSyncService;
use App\Services\PppoeProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Throwable;

class PppoeAccountController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'presence' => 'nullable|in:present,missing',
            'device_id' => 'nullable|integer|exists:mikrotik_devices,id',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $presence = $validated['presence'] ?? '';
        $deviceId = $validated['device_id'] ?? '';

        $accounts = PppoeAccount::query()
            ->with(['mikrotikDevice', 'customer'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('service', 'like', "%{$search}%")
                        ->orWhere('profile', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('mikrotikDevice', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($presence === 'present', fn ($query) => $query->where('is_present', true))
            ->when($presence === 'missing', fn ($query) => $query->where('is_present', false))
            ->when($deviceId !== '', fn ($query) => $query->where('mikrotik_device_id', $deviceId))
            ->latest('last_synced_at')
            ->paginate(15)
            ->withQueryString();

        $devices = MikrotikDevice::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view(
            'pppoe-accounts.index',
            compact('accounts', 'devices', 'search', 'presence', 'deviceId')
        );
    }

    public function sync()
    {
        $exitCode = Artisan::call('mikrotik:sync-all-pppoe');

        if ($exitCode !== 0) {
            return redirect()
                ->route('pppoe-accounts.index')
                ->with('error', 'Sinkronisasi PPPoE gagal. Periksa koneksi MikroTik dan log aplikasi.');
        }

        return redirect()
            ->route('pppoe-accounts.index')
            ->with(
                'success',
                'Sinkronisasi selesai: PPP Profile, PPP Secret, dan sesi aktif telah diperbarui dari MikroTik.'
            );
    }

    public function activeSessions(
        Request $request,
        MikrotikSyncService $syncService
    ) {
        $validated = $request->validate([
            'device_id' => 'nullable|integer|exists:mikrotik_devices,id',
        ]);

        $devices = MikrotikDevice::query()
            ->where('connection_status', '!=', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name']);
        $deviceId = $validated['device_id'] ?? null;
        $error = null;

        if ($deviceId) {
            $device = MikrotikDevice::findOrFail($deviceId);

            try {
                $syncService->sync($device);
            } catch (Throwable $exception) {
                report($exception);
                $error = 'Sinkronisasi live gagal. Menampilkan snapshot sesi terakhir yang tersimpan.';
            }
        }

        $sessionRecords = PppoeActiveSession::query()
            ->with('mikrotikDevice')
            ->where('is_active', true)
            ->when($deviceId, fn ($query) => $query->where(
                'mikrotik_device_id',
                $deviceId
            ))
            ->orderBy('username')
            ->get();

        $accounts = PppoeAccount::query()
            ->with('customer')
            ->whereIn(
                'mikrotik_device_id',
                $sessionRecords->pluck('mikrotik_device_id')->unique()
            )
            ->whereIn('username', $sessionRecords->pluck('username')->unique())
            ->get()
            ->keyBy(fn (PppoeAccount $account) => $account->mikrotik_device_id.'|'.$account->username
            );

        $sessions = $sessionRecords->map(function (
            PppoeActiveSession $session
        ) use ($accounts): array {
            $account = $accounts->get(
                $session->mikrotik_device_id.'|'.$session->username
            );

            return [
                'name' => $session->username,
                'customer' => $account?->customer?->name ?? '-',
                'address' => $session->address,
                'uptime' => $session->uptime,
                'caller-id' => $session->caller_id,
                'profile' => $session->profile,
                'service' => $session->service,
                'device' => $session->mikrotikDevice?->name,
                'last-synced-at' => $session->last_synced_at,
            ];
        });

        return view('pppoe-accounts.sessions', compact(
            'devices',
            'deviceId',
            'sessions',
            'error'
        ));
    }

    public function provision(
        PppoeAccount $pppoeAccount,
        PppoeProvisioningService $provisioningService,
        AuditLogService $auditLog
    ) {
        if (! $pppoeAccount->encrypted_password) {
            return redirect()
                ->route('pppoe-accounts.index')
                ->with('error', 'Password akun PPPoE tidak tersimpan, sehingga provisioning ulang tidak dapat dilakukan.');
        }

        try {
            $action = $provisioningService->provision(
                $pppoeAccount,
                Crypt::decryptString($pppoeAccount->encrypted_password)
            );

            $pppoeAccount->update([
                'is_present' => true,
                'provision_status' => 'provisioned',
                'provisioned_at' => now(),
                'provision_error' => null,
                'last_synced_at' => now(),
            ]);

            $auditLog->record(
                'pppoe.provisioned',
                $pppoeAccount,
                "Akun PPPoE {$pppoeAccount->username} berhasil diprovisioning.",
                ['provision_action' => $action]
            );

            return redirect()
                ->route('pppoe-accounts.index')
                ->with(
                    'success',
                    "Akun {$pppoeAccount->username} berhasil ".
                    ($action === 'updated' ? 'diperbarui' : 'dibuat').
                    ' di MikroTik.'
                );
        } catch (Throwable $exception) {
            report($exception);

            $pppoeAccount->update([
                'provision_status' => 'failed',
                'provision_error' => 'Provisioning ke MikroTik gagal. Periksa koneksi dan konfigurasi.',
            ]);

            return redirect()
                ->route('pppoe-accounts.index')
                ->with('error', 'Provisioning gagal. Periksa koneksi MikroTik, konfigurasi API, dan MIKROTIK_ALLOW_REAL_ACTIONS.');
        }
    }

    public function edit(PppoeAccount $pppoeAccount)
    {
        $pppoeAccount->load('mikrotikDevice');

        return view('pppoe-accounts.edit', compact('pppoeAccount'));
    }

    public function update(
        Request $request,
        PppoeAccount $pppoeAccount,
        PppoeProvisioningService $provisioningService,
        AuditLogService $auditLog
    ) {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pppoe_accounts', 'username')
                    ->where(fn ($query) => $query->where(
                        'mikrotik_device_id',
                        $pppoeAccount->mikrotik_device_id
                    ))
                    ->ignore($pppoeAccount->id),
            ],
            'password' => 'nullable|string|min:1|max:255',
            'service' => 'required|string|max:255',
            'profile' => 'nullable|string|max:255',
            'local_address' => 'nullable|string|max:255',
            'remote_address' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:2000',
        ]);

        $oldUsername = $pppoeAccount->username;
        $password = $validated['password'] ?? null;

        $pppoeAccount->update([
            'username' => trim($validated['username']),
            'service' => trim($validated['service']),
            'profile' => $validated['profile'] ?? null,
            'local_address' => $validated['local_address'] ?? null,
            'remote_address' => $validated['remote_address'] ?? null,
            'comment' => $validated['comment'] ?? null,
            ...($password !== null
                ? ['encrypted_password' => $provisioningService->encryptPassword($password)]
                : []),
        ]);

        $pppoeAccount->refresh();

        if (! config('mikrotik.allow_real_actions')) {
            $pppoeAccount->update([
                'provision_status' => 'pending',
                'provision_error' => 'Perubahan tersimpan di web dan menunggu sinkronisasi real MikroTik.',
            ]);

            return redirect()
                ->route('pppoe-accounts.index')
                ->with('success', 'Akun PPPoE diperbarui di web. Aktifkan real action untuk mengirim perubahan ke MikroTik.');
        }

        try {
            $action = $provisioningService->update(
                $pppoeAccount,
                $oldUsername,
                $password
            );

            $pppoeAccount->update([
                'is_present' => true,
                'provision_status' => 'provisioned',
                'provisioned_at' => now(),
                'provision_error' => null,
                'last_synced_at' => now(),
            ]);

            $auditLog->record(
                'pppoe.updated',
                $pppoeAccount,
                "Akun PPPoE {$pppoeAccount->username} diperbarui.",
                ['provision_action' => $action]
            );

            return redirect()
                ->route('pppoe-accounts.index')
                ->with(
                    'success',
                    'Akun PPPoE berhasil '.
                    ($action === 'updated' ? 'diperbarui' : 'dibuat').
                    ' di MikroTik.'
                );
        } catch (Throwable $exception) {
            report($exception);

            $pppoeAccount->update([
                'provision_status' => 'failed',
                'provision_error' => 'Perubahan tersimpan di web, tetapi gagal dikirim ke MikroTik.',
            ]);

            return redirect()
                ->route('pppoe-accounts.index')
                ->with('error', 'Perubahan tersimpan di web, tetapi gagal dikirim ke MikroTik.');
        }
    }

    public function disable(
        PppoeAccount $pppoeAccount,
        PppoeProvisioningService $provisioningService,
        AuditLogService $auditLog
    ) {
        try {
            $disconnected = $provisioningService->setDisabled($pppoeAccount, true);

            $pppoeAccount->update([
                'disabled' => true,
                'is_present' => true,
                'provision_status' => 'provisioned',
                'provision_error' => null,
                'last_synced_at' => now(),
            ]);

            $auditLog->record(
                'pppoe.disabled',
                $pppoeAccount,
                "Akun PPPoE {$pppoeAccount->username} dinonaktifkan.",
                ['disconnected_sessions' => $disconnected]
            );

            return redirect()
                ->route('pppoe-accounts.index')
                ->with('success', "Akun {$pppoeAccount->username} dinonaktifkan. Sesi terputus: {$disconnected}.");
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('pppoe-accounts.index')
                ->with('error', 'Akun PPPoE gagal dinonaktifkan. Periksa koneksi MikroTik.');
        }
    }

    public function enable(
        PppoeAccount $pppoeAccount,
        PppoeProvisioningService $provisioningService,
        AuditLogService $auditLog
    ) {
        try {
            $provisioningService->setDisabled($pppoeAccount, false);

            $pppoeAccount->update([
                'disabled' => false,
                'is_present' => true,
                'provision_status' => 'provisioned',
                'provision_error' => null,
                'last_synced_at' => now(),
            ]);

            $auditLog->record(
                'pppoe.enabled',
                $pppoeAccount,
                "Akun PPPoE {$pppoeAccount->username} diaktifkan."
            );

            return redirect()
                ->route('pppoe-accounts.index')
                ->with('success', "Akun {$pppoeAccount->username} diaktifkan kembali.");
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('pppoe-accounts.index')
                ->with('error', 'Akun PPPoE gagal diaktifkan. Periksa koneksi MikroTik.');
        }
    }
}
