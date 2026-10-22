<?php

namespace Database\Factories;

use App\Models\NetworkLink;
use App\Models\NetworkNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkLink>
 */
class NetworkLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_node_id' => NetworkNode::factory(),
            'to_node_id' => NetworkNode::factory(),
            'status' => 'unknown',
        ];
    }
}
