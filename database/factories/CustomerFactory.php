<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\InternetPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_code' => fake()->unique()->numerify('CUST-####'),
            'internet_package_id' => InternetPackage::factory(),
            'name' => fake()->name(),
            'whatsapp_number' => fake()->numerify('62812########'),
            'address' => fake()->address(),
            'installation_date' => today(),
            'due_day' => today()->day,
            'status' => 'active',
        ];
    }
}
