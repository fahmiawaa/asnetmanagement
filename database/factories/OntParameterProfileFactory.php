<?php

namespace Database\Factories;

use App\Models\OntParameterProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OntParameterProfile>
 */
class OntParameterProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'manufacturer' => fake()->randomElement(['ZTE', 'Huawei', 'FiberHome']),
            'oui' => null,
            'product_class' => null,
            'pon_mode' => null,
            'ssid_paths' => ['InternetGatewayDevice.WiFi.SSID.1.SSID'],
            'wifi_password_paths' => ['VirtualParameters.WlanPassword'],
            'rx_power_rules' => null,
            'temperature_rules' => null,
            'wan_ip_paths' => null,
            'active_client_paths' => null,
            'is_verified' => true,
            'verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_verified' => false,
            'verified_at' => null,
        ]);
    }

    public function forDevice(string $manufacturer, ?string $productClass = null, ?string $ponMode = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'manufacturer' => $manufacturer,
            'product_class' => $productClass,
            'pon_mode' => $ponMode,
        ]);
    }
}
