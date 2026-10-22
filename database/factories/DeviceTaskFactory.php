<?php

namespace Database\Factories;

use App\Models\DeviceTask;
use App\Models\OntDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceTask>
 */
class DeviceTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ont_device_id' => OntDevice::factory(),
            'type' => DeviceTask::TYPE_SUMMON,
            'status' => DeviceTask::STATUS_PENDING,
            'requested_by' => User::factory(),
            'attempts' => 0,
            'safe_summary' => [
                'type' => DeviceTask::TYPE_SUMMON,
            ],
            'expires_at' => now()->addMinutes(30),
        ];
    }

    public function reboot(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DeviceTask::TYPE_REBOOT,
            'safe_summary' => ['type' => DeviceTask::TYPE_REBOOT],
        ]);
    }

    public function setWifi(string $ssid = 'ASNet-Baru'): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DeviceTask::TYPE_SET_WIFI,
            'safe_summary' => ['type' => DeviceTask::TYPE_SET_WIFI, 'ssid' => $ssid],
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeviceTask::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeviceTask::STATUS_VERIFIED,
            'accepted_at' => now(),
            'verified_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function failed(string $message = 'GenieACS menolak perintah'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeviceTask::STATUS_FAILED,
            'failed_at' => now(),
            'error_code' => 'GENIEACS_ERROR',
            'error_message' => $message,
            'attempts' => 1,
        ]);
    }
}
