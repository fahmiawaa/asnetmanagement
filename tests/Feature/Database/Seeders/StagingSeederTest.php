<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Customer;
use App\Models\DeviceTask;
use App\Models\GenieacsServer;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\MikrotikDevice;
use App\Models\NetworkNode;
use App\Models\OntDevice;
use App\Models\OntParameterProfile;
use App\Models\PppoeAccount;
use App\Models\User;
use Database\Seeders\StagingSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class StagingSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_creates_masked_staging_dataset(): void
    {
        Artisan::call('db:seed', ['--class' => StagingSeeder::class, '--force' => true]);

        $expectedCustomers = max(1, (int) config('staging.customer_count'));

        $this->assertSame($expectedCustomers, Customer::query()->count());
        $this->assertSame($expectedCustomers, OntDevice::query()->count());
        $this->assertSame($expectedCustomers * 2, Invoice::query()->count());

        Customer::query()->each(function (Customer $customer): void {
            $this->assertMatchesRegularExpression('/^STG-\d{4}$/', $customer->customer_code);
            $this->assertMatchesRegularExpression('/^Pelanggan Uji \d{2}$/', $customer->name);
            $this->assertMatchesRegularExpression('/^0812000000\d{2}$/', (string) $customer->whatsapp_number);
            $this->assertStringContainsString('Staging', (string) $customer->address);
        });

        OntDevice::query()->each(function (OntDevice $device): void {
            $this->assertMatchesRegularExpression('/^STG-DEV-\d{4}$/', $device->genieacs_device_id);
            $this->assertMatchesRegularExpression('/^STGONT\d{8}$/', (string) $device->serial_number);
            $this->assertNotNull($device->customer_id);
            $this->assertNotNull($device->network_node_id);
        });

        PppoeAccount::query()->each(function (PppoeAccount $account): void {
            $this->assertMatchesRegularExpression('/^stg-\d{4}$/', $account->username);
            $this->assertSame('staging-only', Crypt::decryptString((string) $account->encrypted_password));
        });

        foreach (['owner', 'admin', 'teknisi', 'pelanggan'] as $role) {
            $this->assertDatabaseHas('users', [
                'email' => "{$role}@".config('staging.email_domain'),
                'role' => $role,
            ]);
        }

        $this->assertSame(3, InternetPackage::query()->count());
        $this->assertSame(1, GenieacsServer::query()->count());
        $this->assertSame(1, MikrotikDevice::query()->count());
        $this->assertSame('staging-only', Crypt::decryptString(
            (string) MikrotikDevice::query()->firstOrFail()->encrypted_password
        ));
        $this->assertGreaterThanOrEqual(
            6,
            NetworkNode::query()->whereIn('type', ['server', 'olt', 'pon_port', 'odc', 'odp', 'ont'])->count()
        );
        $this->assertSame(
            1,
            OntParameterProfile::query()->where('is_verified', true)->count()
        );
        $this->assertSame(2, DeviceTask::query()->count());

        $this->assertGreaterThanOrEqual(1, User::query()->where('role', 'pelanggan')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => StagingSeeder::class, '--force' => true]);

        $counts = [
            Customer::query()->count(),
            OntDevice::query()->count(),
            Invoice::query()->count(),
            User::query()->count(),
            NetworkNode::query()->count(),
        ];

        Artisan::call('db:seed', ['--class' => StagingSeeder::class, '--force' => true]);

        $this->assertSame($counts, [
            Customer::query()->count(),
            OntDevice::query()->count(),
            Invoice::query()->count(),
            User::query()->count(),
            NetworkNode::query()->count(),
        ]);
    }

    public function test_seeder_refuses_non_staging_environment(): void
    {
        $this->app['env'] = 'production';

        Artisan::call('db:seed', ['--class' => StagingSeeder::class, '--force' => true]);

        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, OntDevice::query()->count());
    }
}
