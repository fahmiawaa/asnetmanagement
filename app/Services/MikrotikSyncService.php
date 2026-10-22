<?php

namespace App\Services;

use App\Models\InternetPackage;
use App\Models\MikrotikDevice;
use App\Models\PppoeAccount;
use App\Models\PppoeActiveSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MikrotikSyncService
{
    public function __construct(
        private readonly MikrotikConnectionService $connectionService
    ) {}

    public function sync(MikrotikDevice $device): array
    {
        // Semua pembacaan dilakukan sebelum transaksi database agar lock singkat.
        $profiles = $this->connectionService->getPppProfiles($device);
        $secrets = $this->connectionService->getPppoeSecrets($device);
        $sessions = $this->connectionService->getActivePppoeSessions($device);
        $syncedAt = now();

        $result = DB::transaction(function () use (
            $device,
            $profiles,
            $secrets,
            $sessions,
            $syncedAt
        ): array {
            $profileResult = $this->syncProfiles($device, $profiles, $syncedAt);
            $secretResult = $this->syncSecrets($device, $secrets, $syncedAt);
            $sessionResult = $this->syncSessions(
                $device,
                $sessions,
                $secrets,
                $syncedAt
            );

            $device->update([
                'connection_status' => 'connected',
                'last_sync_at' => $syncedAt,
            ]);

            return [
                'profiles' => $profileResult,
                'secrets' => $secretResult,
                'sessions' => $sessionResult,
                'synced_at' => $syncedAt,
            ];
        });

        return $result;
    }

    private function syncProfiles(
        MikrotikDevice $device,
        array $profiles,
        $syncedAt
    ): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $seen = [];

        foreach ($profiles as $profile) {
            $name = trim((string) ($profile['name'] ?? ''));

            if (
                $name === ''
                || in_array($name, ['default', 'default-encryption'], true)
            ) {
                $skipped++;

                continue;
            }

            $seen[] = $name;
            [$downloadSpeed, $uploadSpeed] = $this->profileSpeeds(
                (string) ($profile['rate-limit'] ?? '')
            );

            $package = InternetPackage::withTrashed()
                ->where('mikrotik_profile', $name)
                ->where(function ($query) use ($device) {
                    $query
                        ->where('mikrotik_device_id', $device->id)
                        ->orWhereNull('mikrotik_device_id');
                })
                ->first();

            if ($package) {
                if ($package->trashed()) {
                    $package->restore();
                }

                $package->update([
                    'mikrotik_device_id' => $device->id,
                    'download_speed' => $downloadSpeed,
                    'upload_speed' => $uploadSpeed,
                    'sync_source' => 'mikrotik',
                    'is_present' => true,
                    'last_synced_at' => $syncedAt,
                ]);
                $updated++;

                continue;
            }

            InternetPackage::create([
                'mikrotik_device_id' => $device->id,
                'package_code' => $this->profilePackageCode($device, $name),
                'name' => $name,
                'download_speed' => $downloadSpeed,
                'upload_speed' => $uploadSpeed,
                'price' => 0,
                'mikrotik_profile' => $name,
                'sync_source' => 'mikrotik',
                'is_present' => true,
                'last_synced_at' => $syncedAt,
                'status' => 'inactive',
                'description' => 'Diimpor otomatis dari PPP Profile MikroTik '.
                    $device->name.
                    '. Atur harga lalu aktifkan sebelum digunakan untuk billing.',
            ]);
            $created++;
        }

        $missingQuery = InternetPackage::query()
            ->where('mikrotik_device_id', $device->id)
            ->where('sync_source', 'mikrotik');

        if ($seen === []) {
            $missing = $missingQuery->update([
                'is_present' => false,
                'status' => 'inactive',
            ]);
        } else {
            $missing = $missingQuery
                ->whereNotIn('mikrotik_profile', $seen)
                ->update([
                    'is_present' => false,
                    'status' => 'inactive',
                ]);
        }

        return compact('created', 'updated', 'missing', 'skipped');
    }

    private function syncSecrets(
        MikrotikDevice $device,
        array $secrets,
        $syncedAt
    ): array {
        $skipped = 0;
        $rowsByUsername = [];
        $existingAccounts = PppoeAccount::query()
            ->where('mikrotik_device_id', $device->id)
            ->get(['username', 'is_present'])
            ->keyBy('username');

        foreach ($secrets as $secret) {
            $username = trim((string) ($secret['name'] ?? ''));

            if ($username === '') {
                $skipped++;

                continue;
            }

            $disabledValue = strtolower((string) ($secret['disabled'] ?? 'false'));
            $disabled = in_array($disabledValue, ['true', 'yes', '1'], true);

            $rowsByUsername[$username] = [
                'mikrotik_device_id' => $device->id,
                'username' => $username,
                'service' => $secret['service'] ?? null,
                'profile' => $secret['profile'] ?? null,
                'disabled' => $disabled,
                'is_present' => true,
                'provision_status' => 'synced',
                'provision_error' => null,
                'local_address' => $secret['local-address'] ?? null,
                'remote_address' => $secret['remote-address'] ?? null,
                'comment' => $secret['comment'] ?? null,
                'last_synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ];
        }

        $seenUsernames = array_keys($rowsByUsername);
        $created = collect($seenUsernames)
            ->reject(fn (string $username): bool => $existingAccounts->has($username))
            ->count();
        $updated = count($seenUsernames) - $created;
        $missing = $existingAccounts
            ->filter(fn (PppoeAccount $account): bool => $account->is_present)
            ->keys()
            ->diff($seenUsernames)
            ->count();

        PppoeAccount::query()
            ->where('mikrotik_device_id', $device->id)
            ->where('is_present', true)
            ->update([
                'is_present' => false,
                'updated_at' => $syncedAt,
            ]);

        if ($rowsByUsername !== []) {
            PppoeAccount::query()->upsert(
                array_values($rowsByUsername),
                ['mikrotik_device_id', 'username'],
                [
                    'service',
                    'profile',
                    'disabled',
                    'is_present',
                    'provision_status',
                    'provision_error',
                    'local_address',
                    'remote_address',
                    'comment',
                    'last_synced_at',
                    'updated_at',
                ]
            );
        }

        return compact('created', 'updated', 'missing', 'skipped');
    }

    private function syncSessions(
        MikrotikDevice $device,
        array $sessions,
        array $secrets,
        $syncedAt
    ): array {
        $profileByUsername = collect($secrets)
            ->filter(fn ($secret) => ! empty($secret['name']))
            ->mapWithKeys(fn ($secret) => [
                (string) $secret['name'] => $secret['profile'] ?? null,
            ]);

        PppoeActiveSession::query()
            ->where('mikrotik_device_id', $device->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_at' => $syncedAt,
            ]);

        $skipped = 0;
        $rowsBySessionId = [];
        $existingSessionIds = PppoeActiveSession::query()
            ->where('mikrotik_device_id', $device->id)
            ->pluck('router_session_id')
            ->flip();

        foreach ($sessions as $session) {
            $username = trim((string) ($session['name'] ?? ''));

            if ($username === '') {
                $skipped++;

                continue;
            }

            $routerSessionId = (string) ($session['.id'] ?? '');
            if ($routerSessionId === '') {
                $routerSessionId = hash(
                    'sha256',
                    implode('|', [
                        $username,
                        (string) ($session['caller-id'] ?? ''),
                        (string) ($session['address'] ?? ''),
                    ])
                );
            }

            $rowsBySessionId[$routerSessionId] = [
                'mikrotik_device_id' => $device->id,
                'router_session_id' => $routerSessionId,
                'username' => $username,
                'service' => $session['service'] ?? null,
                'profile' => $profileByUsername->get($username),
                'address' => $session['address'] ?? null,
                'caller_id' => $session['caller-id'] ?? null,
                'uptime' => $session['uptime'] ?? null,
                'is_active' => true,
                'last_synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ];
        }

        $activeSessionIds = array_keys($rowsBySessionId);
        $created = collect($activeSessionIds)
            ->reject(fn (string $sessionId): bool => $existingSessionIds->has($sessionId))
            ->count();
        $updated = count($activeSessionIds) - $created;

        if ($rowsBySessionId !== []) {
            PppoeActiveSession::query()->upsert(
                array_values($rowsBySessionId),
                ['mikrotik_device_id', 'router_session_id'],
                [
                    'username',
                    'service',
                    'profile',
                    'address',
                    'caller_id',
                    'uptime',
                    'is_active',
                    'last_synced_at',
                    'updated_at',
                ]
            );
        }

        $inactive = PppoeActiveSession::query()
            ->where('mikrotik_device_id', $device->id)
            ->where('is_active', false)
            ->count();

        return [
            'created' => $created,
            'updated' => $updated,
            'inactive' => $inactive,
            'skipped' => $skipped,
            'active' => $created + $updated,
        ];
    }

    private function profilePackageCode(
        MikrotikDevice $device,
        string $profileName
    ): string {
        return 'MT-'.$device->id.'-'.
            Str::upper(substr(hash('sha256', $profileName), 0, 8));
    }

    private function profileSpeeds(string $rateLimit): array
    {
        $rateLimit = trim($rateLimit);

        if ($rateLimit === '') {
            return ['Tidak dibatasi', 'Tidak dibatasi'];
        }

        $baseRate = preg_split('/\s+/', $rateLimit)[0] ?? '';
        $parts = explode('/', $baseRate, 2);
        $upload = $this->formatRate($parts[0] ?? '');
        $download = $this->formatRate($parts[1] ?? ($parts[0] ?? ''));

        return [$download, $upload];
    }

    private function formatRate(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '0') {
            return 'Tidak dibatasi';
        }

        if (preg_match('/^([0-9.]+)([kKmMgG])$/', $value, $matches)) {
            $unit = match (strtolower($matches[2])) {
                'k' => 'Kbps',
                'm' => 'Mbps',
                'g' => 'Gbps',
            };

            $number = rtrim(
                rtrim(number_format((float) $matches[1], 2, '.', ''), '0'),
                '.'
            );

            return $number.' '.$unit;
        }

        if (ctype_digit($value)) {
            return number_format(((int) $value) / 1_000_000, 2).' Mbps';
        }

        return $value;
    }
}
