<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'invoice_number' => fake()->unique()->numerify('INV-########'),
            'period_start' => today()->startOfMonth(),
            'period_end' => today()->endOfMonth(),
            'due_date' => today(),
            'total_amount' => 150000,
            'status' => 'unpaid',
        ];
    }
}
