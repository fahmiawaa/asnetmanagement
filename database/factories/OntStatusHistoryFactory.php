<?php

namespace Database\Factories;

use App\Models\OntDevice;
use App\Models\OntStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OntStatusHistory>
 */
class OntStatusHistoryFactory extends Factory
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
            'old_status' => 'warning',
            'new_status' => 'online',
            'last_inform_at' => now(),
            'observed_at' => now(),
            'source' => 'genieacs_sync',
        ];
    }
}
