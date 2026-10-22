<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\MikrotikDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerMikrotikPackageSelectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_form_automatically_selects_the_only_active_mikrotik(): void
    {
        $owner = $this->createOwner();
        $device = $this->createDevice('Router Desa A', '10.10.10.1');
        $package = InternetPackage::factory()->create([
            'mikrotik_device_id' => $device->id,
            'name' => 'Paket Desa A 20 Mbps',
            'is_present' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->get(route('customers.create'));

        $response
            ->assertOk()
            ->assertSee('MikroTik dipilih otomatis karena hanya ada satu perangkat aktif.')
            ->assertSee('Paket Desa A 20 Mbps');
        $this->assertMatchesRegularExpression(
            '/<option[^>]*value="'.$device->id.'"[^>]*selected[^>]*>/',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<option\\s+value="'.$package->id.'"[^>]*>/',
            $response->getContent()
        );
    }

    public function test_rejects_package_from_a_different_mikrotik(): void
    {
        $owner = $this->createOwner();
        $deviceA = $this->createDevice('Router Desa A', '10.10.10.1');
        $deviceB = $this->createDevice('Router Desa B', '10.20.20.1');
        $packageB = InternetPackage::factory()->create([
            'mikrotik_device_id' => $deviceB->id,
            'is_present' => true,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('customers.store'), $this->customerPayload([
                'mikrotik_device_id' => $deviceA->id,
                'internet_package_id' => $packageB->id,
            ]))
            ->assertSessionHasErrors('internet_package_id');

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_stores_customer_with_package_from_selected_mikrotik(): void
    {
        $owner = $this->createOwner();
        $device = $this->createDevice('Router Desa A', '10.10.10.1');
        $package = InternetPackage::factory()->create([
            'mikrotik_device_id' => $device->id,
            'is_present' => true,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('customers.store'), $this->customerPayload([
                'mikrotik_device_id' => $device->id,
                'internet_package_id' => $package->id,
            ]))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'customer_code' => 'AS-TEST-001',
            'internet_package_id' => $package->id,
            'status' => 'active',
        ]);
    }

    private function createOwner(): User
    {
        return User::factory()->create([
            'role' => 'owner',
            'active' => true,
        ]);
    }

    private function createDevice(string $name, string $host): MikrotikDevice
    {
        return MikrotikDevice::query()->create([
            'name' => $name,
            'host' => $host,
            'api_port' => 8728,
            'use_ssl' => false,
            'username' => 'billing-automation',
            'encrypted_password' => 'encrypted-test-value',
            'connection_status' => 'connected',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function customerPayload(array $overrides): array
    {
        return array_merge([
            'customer_code' => 'AS-TEST-001',
            'name' => 'Pelanggan Uji',
            'whatsapp_number' => '081234567890',
            'address' => 'Desa A, RT 01/RW 01',
            'installation_date' => '2026-09-26',
            'due_day' => 10,
        ], $overrides);
    }
}
