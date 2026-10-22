<?php

namespace Database\Factories;

use App\Models\GenieacsServer;
use App\Models\OntDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OntDevice>
 */
class OntDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'genieacs_server_id' => GenieacsServer::factory(),
            'genieacs_device_id' => fake()->unique()->regexify('[A-F0-9]{6}-[A-Z0-9]{8}-[A-F0-9]{12}'),
            'serial_number' => fake()->unique()->regexify('[A-Z]{4}[0-9]{8}'),
            'manufacturer' => fake()->randomElement(['ZTE', 'Huawei', 'FiberHome']),
            'product_class' => fake()->randomElement(['F660', 'HG8245H', 'AN5506']),
            'pon_mode' => fake()->randomElement(['gpon', 'epon']),
            'inform_interval_seconds' => 200,
            'last_inform_at' => now(),
            'connection_status' => 'online',
            'rx_power_quality' => 'unknown',
            'last_seen_at' => now(),
            'last_synced_at' => now(),
        ];
    }
}
