<?php

namespace Database\Factories;

use App\Models\GenieacsServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenieacsServer>
 */
class GenieacsServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' ACS',
            'base_url' => 'http://'.fake()->unique()->ipv4().':7557',
            'username' => null,
            'encrypted_password' => null,
            'is_active' => true,
            'connection_status' => 'unknown',
        ];
    }
}
