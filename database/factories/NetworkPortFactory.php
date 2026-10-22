<?php

namespace Database\Factories;

use App\Models\NetworkNode;
use App\Models\NetworkPort;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkPort>
 */
class NetworkPortFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'network_node_id' => NetworkNode::factory(),
            'port_number' => fake()->numberBetween(1, 16),
            'port_type' => 'output',
            'capacity' => null,
            'technology' => 'gpon',
        ];
    }
}
