<?php

namespace App\Services;

use App\Jobs\DispatchDeviceTask;
use App\Models\DeviceTask;
use App\Models\OntDevice;
use App\Models\OntParameterProfile;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeviceTaskService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Buat task perangkat lalu kirim ke antrean. Password hanya disimpan terenkripsi
     * dan tidak pernah masuk ke safe_summary, log, atau notifikasi.
     *
     * @param  array<string, mixed>  $wifi
     */
    public function request(
        OntDevice $ontDevice,
        string $type,
        ?User $user = null,
        array $wifi = [],
    ): DeviceTask {
        if (! config('genieacs.tasks.enabled')) {
            throw ValidationException::withMessages([
                'type' => 'Task perangkat sedang dinonaktifkan. Set GENIEACS_TASKS_ENABLED=true untuk mengaktifkan.',
            ]);
        }

        if (! in_array($type, DeviceTask::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Tipe task tidak dikenal.']);
        }

        $openTask = DeviceTask::query()
            ->where('ont_device_id', $ontDevice->getKey())
            ->where('type', $type)
            ->withStatuses(DeviceTask::OPEN_STATUSES)
            ->first();

        if ($openTask !== null) {
            throw ValidationException::withMessages([
                'type' => "Masih ada task {$type} berstatus {$openTask->status} untuk perangkat ini.",
            ]);
        }

        $payload = null;
        $safeSummary = ['type' => $type];

        if ($type === DeviceTask::TYPE_SET_WIFI) {
            $resolved = $this->resolveWifi($ontDevice, $wifi);
            $payload = json_encode([
                'ssid' => $resolved['ssid'],
                'password' => $resolved['password'],
                'ssid_path' => $resolved['ssid_path'],
                'password_path' => $resolved['password_path'],
            ], JSON_THROW_ON_ERROR);
            $safeSummary['ssid'] = $resolved['ssid'];
            $safeSummary['ssid_path'] = $resolved['ssid_path'];
            $safeSummary['profile_id'] = $resolved['profile']->getKey();
            $safeSummary['profile_name'] = $resolved['profile']->name;
        }

        $task = DeviceTask::query()->create([
            'ont_device_id' => $ontDevice->getKey(),
            'type' => $type,
            'status' => DeviceTask::STATUS_PENDING,
            'requested_by' => $user?->getKey(),
            'payload_encrypted' => $payload,
            'safe_summary' => $safeSummary,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(max(1, (int) config('genieacs.tasks.expiry_minutes'))),
        ]);

        $this->audit->record(
            'device_task.requested',
            $task,
            "Task {$type} diminta untuk {$this->deviceLabel($ontDevice)}.",
            [
                'ont_device_id' => $ontDevice->getKey(),
                'serial_number' => $ontDevice->serial_number,
                'safe_summary' => $safeSummary,
            ],
        );

        DispatchDeviceTask::dispatch($task);

        return $task;
    }

    public function cancel(DeviceTask $task, ?User $user = null): DeviceTask
    {
        if (! $task->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'Task sudah berstatus akhir dan tidak dapat dibatalkan.',
            ]);
        }

        $task->fill([
            'status' => DeviceTask::STATUS_CANCELLED,
            'failed_at' => now(),
            'error_code' => 'CANCELLED',
            'error_message' => 'Dibatalkan oleh operator.',
        ])->save();

        $this->audit->record(
            'device_task.cancelled',
            $task,
            "Task {$task->type} dibatalkan oleh ".($user?->name ?? 'sistem').'.',
            ['previous_attempts' => $task->attempts],
        );

        return $task;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ssid: string, password: string, ssid_path: string, password_path: string, profile: OntParameterProfile}
     */
    private function resolveWifi(OntDevice $ontDevice, array $input): array
    {
        $ssid = trim((string) ($input['ssid'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($ssid === '' || mb_strlen($ssid) > 32) {
            throw ValidationException::withMessages([
                'ssid' => 'SSID wajib diisi dan maksimal 32 karakter.',
            ]);
        }

        if (strlen($password) < 8 || strlen($password) > 63) {
            throw ValidationException::withMessages([
                'password' => 'Password WiFi harus 8 sampai 63 karakter.',
            ]);
        }

        $profile = $this->resolveProfile($ontDevice, $input['profile_id'] ?? null);
        $ssidPaths = $profile->ssidPaths();
        $passwordPaths = $profile->passwordPaths();

        if ($ssidPaths === [] || $passwordPaths === []) {
            throw ValidationException::withMessages([
                'profile_id' => "Profil {$profile->name} belum memiliki path SSID atau password.",
            ]);
        }

        return [
            'ssid' => $ssid,
            'password' => $password,
            'ssid_path' => $ssidPaths[0],
            'password_path' => $passwordPaths[0],
            'profile' => $profile,
        ];
    }

    private function resolveProfile(OntDevice $ontDevice, mixed $profileId): OntParameterProfile
    {
        if (filled($profileId)) {
            $profile = OntParameterProfile::query()->find($profileId);

            if ($profile === null) {
                throw ValidationException::withMessages([
                    'profile_id' => 'Profil parameter tidak ditemukan.',
                ]);
            }

            if (! $profile->is_verified) {
                throw ValidationException::withMessages([
                    'profile_id' => "Profil {$profile->name} belum diverifikasi.",
                ]);
            }

            if (! $profile->matches($ontDevice)) {
                throw ValidationException::withMessages([
                    'profile_id' => "Profil {$profile->name} tidak cocok dengan perangkat ini.",
                ]);
            }

            return $profile;
        }

        $profile = OntParameterProfile::query()
            ->where('is_verified', true)
            ->matching($ontDevice)
            ->get()
            ->sortByDesc(fn (OntParameterProfile $candidate): int => $candidate->specificity())
            ->first();

        if ($profile === null) {
            throw ValidationException::withMessages([
                'profile_id' => 'Belum ada profil parameter terverifikasi untuk perangkat ini.',
            ]);
        }

        return $profile;
    }

    private function deviceLabel(OntDevice $ontDevice): string
    {
        return $ontDevice->serial_number ?: $ontDevice->genieacs_device_id;
    }
}
