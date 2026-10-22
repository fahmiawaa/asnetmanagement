<?php

namespace Database\Factories;

use App\Models\InternetPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternetPackage>
 */
class InternetPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_code' => fake()->unique()->bothify('PKG-####'),
            'name' => fake()->randomElement(['10 Mbps', '20 Mbps', '30 Mbps']),
            'download_speed' => '20 Mbps',
            'upload_speed' => '10 Mbps',
            'price' => 150000,
            'status' => 'active',
        ];
    }
}
