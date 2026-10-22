<?php

namespace Database\Factories;

use App\Models\NetworkNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkNode>
 */
class NetworkNodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'type' => 'odp',
            'name' => fake()->unique()->bothify('Node-####'),
            'code' => null,
            'technology' => 'gpon',
            'latitude' => null,
            'longitude' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function ofType(string $type, ?NetworkNode $parent = null): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'parent_id' => $parent?->id,
        ]);
    }
}
