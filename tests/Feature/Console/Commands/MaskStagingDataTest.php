<?php

namespace Tests\Feature\Console\Commands;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\GenieacsServer;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\MikrotikDevice;
use App\Models\OntDevice;
use App\Models\Payment;
use App\Models\PppoeAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaskStagingDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_masking_replaces_pii_credentials_and_device_identity(): void
    {
        [$customer, $account, $device] = $this->realisticDataset();

        $this->artisan('asnet:mask-staging')->assertSuccessful();

        $customer->refresh();
        $account->refresh();
        $device->refresh();

        $this->assertSame('STG-'.str_pad((string) $customer->getKey(), 4, '0', STR_PAD_LEFT), $customer->customer_code);
        $this->assertSame('Pelanggan Uji '.$customer->getKey(), $customer->name);
        $this->assertNull($customer->latitude);
        $this->assertStringContainsString('Staging', (string) $customer->address);
        $this->assertStringNotContainsString('Jl. Merdeka', (string) $customer->address);

        $this->assertSame('stg-'.str_pad((string) $account->getKey(), 4, '0', STR_PAD_LEFT), $account->username);
        $this->assertSame('staging-only', Crypt::decryptString((string) $account->encrypted_password));

        $this->assertSame('STG-DEV-'.str_pad((string) $device->getKey(), 4, '0', STR_PAD_LEFT), $device->genieacs_device_id);
        $this->assertSame('STGONT'.str_pad((string) $device->getKey(), 8, '0', STR_PAD_LEFT), $device->serial_number);
        $this->assertStringStartsWith('02:00:00:', (string) $device->pon_mac);

        $user = User::query()->where('role', 'owner')->firstOrFail();
        $this->assertSame('user'.$user->getKey().'@'.config('staging.email_domain'), $user->email);
        $this->assertTrue(Hash::check((string) config('staging.password'), $user->password));

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('INV-STG-'.str_pad((string) $invoice->getKey(), 6, '0', STR_PAD_LEFT), $invoice->invoice_number);

        $mikrotik = MikrotikDevice::query()->firstOrFail();
        $this->assertSame('staging-only', Crypt::decryptString((string) $mikrotik->encrypted_password));

        $server = GenieacsServer::query()->firstOrFail();
        $this->assertNull($server->username);
        $this->assertNull($server->encrypted_password);

        $this->assertSame(0, DB::table('sessions')->count());
        AuditLog::query()->where('action', 'staging.data_masked')->firstOrFail();
    }

    public function test_masking_keeps_unique_constraints_intact_for_multiple_rows(): void
    {
        $package = InternetPackage::factory()->create();
        $mikrotik = $this->mikrotik();

        for ($i = 1; $i <= 3; $i++) {
            $pppoe = PppoeAccount::query()->create([
                'mikrotik_device_id' => $mikrotik->getKey(),
                'username' => "asli-{$i}@contoh.net",
            ]);

            Customer::query()->create([
                'customer_code' => "CUST-{$i}X",
                'internet_package_id' => $package->getKey(),
                'pppoe_account_id' => $pppoe->getKey(),
                'name' => 'Pelanggan Asli '.$i,
                'whatsapp_number' => '62812345'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'address' => 'Jl. Merdeka '.$i,
                'due_day' => 10,
                'status' => 'active',
            ]);
        }

        $this->artisan('asnet:mask-staging')->assertSuccessful();

        $this->assertSame(3, Customer::query()->where('customer_code', 'like', 'STG-%')->count());
        $this->assertSame(3, PppoeAccount::query()->count());
        $this->assertSame(
            3,
            PppoeAccount::query()->select('username')->distinct()->count('username')
        );
    }

    public function test_dry_run_reports_plan_without_changing_data(): void
    {
        $this->realisticDataset();

        $before = Customer::query()->firstOrFail()->name;

        $this->artisan('asnet:mask-staging', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, Customer::query()->firstOrFail()->name);
        $this->assertSame(0, AuditLog::query()->where('action', 'staging.data_masked')->count());
    }

    public function test_masking_refuses_production_environment(): void
    {
        $this->realisticDataset();
        $this->app['env'] = 'production';

        $this->artisan('asnet:mask-staging')->assertFailed();

        $this->assertSame('Pelanggan Merdeka', Customer::query()->firstOrFail()->name);
    }

    private function realisticDataset(): array
    {
        $package = InternetPackage::factory()->create();
        $mikrotik = $this->mikrotik();

        $account = PppoeAccount::query()->create([
            'mikrotik_device_id' => $mikrotik->getKey(),
            'username' => 'budi@contoh.net',
            'encrypted_password' => Crypt::encryptString('rahasia-asli'),
            'comment' => 'Pelanggan budi',
        ]);

        $customer = Customer::query()->create([
            'customer_code' => 'CUST-0001',
            'internet_package_id' => $package->getKey(),
            'pppoe_account_id' => $account->getKey(),
            'name' => 'Pelanggan Merdeka',
            'whatsapp_number' => '6281234567890',
            'address' => 'Jl. Merdeka No. 1, Kota Contoh',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'due_day' => 10,
            'status' => 'active',
        ]);

        $server = GenieacsServer::query()->create([
            'name' => 'ACS Produksi',
            'base_url' => 'https://acs.contoh.net:7557',
            'username' => 'acs-user',
            'encrypted_password' => Crypt::encryptString('acs-secret'),
        ]);

        $device = OntDevice::query()->create([
            'customer_id' => $customer->getKey(),
            'genieacs_server_id' => $server->getKey(),
            'genieacs_device_id' => 'FCJ1234ABCD',
            'serial_number' => 'ZTEG12345678',
            'pon_mac' => 'F8:1A:67:12:34:56',
            'pppoe_mac' => 'F8:1A:67:65:43:21',
            'ip_address' => '192.168.1.50',
            'pppoe_ip' => '10.0.0.50',
            'manufacturer' => 'ZTE',
            'product_class' => 'F660',
            'pon_mode' => 'gpon',
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->getKey(),
            'invoice_number' => 'INV-20260101001',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'due_date' => now()->addDays(10),
            'total_amount' => 150000,
            'status' => 'paid',
            'notes' => 'Tagihan budi',
        ]);

        Payment::query()->create([
            'invoice_id' => $invoice->getKey(),
            'payment_date' => now()->toDateString(),
            'amount' => 150000,
            'method' => 'bank_transfer',
            'status' => 'verified',
            'proof_path' => 'proofs/budi-receipt.jpg',
            'notes' => 'Bukti atas nama budi',
        ]);

        User::query()->create([
            'name' => 'Pemilik Asli',
            'email' => 'pemilik@contoh.net',
            'password' => Hash::make('password-asli'),
            'role' => 'owner',
            'active' => true,
        ]);

        DB::table('sessions')->insert([
            'id' => 'session-asli',
            'user_id' => null,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Mozilla/5.0',
            'payload' => base64_encode('data-asli'),
            'last_activity' => now()->getTimestamp(),
        ]);

        return [$customer, $account, $device];
    }

    private function mikrotik(): MikrotikDevice
    {
        return MikrotikDevice::query()->create([
            'name' => 'MikroTik Kantor',
            'host' => '192.168.88.1',
            'username' => 'admin',
            'encrypted_password' => Crypt::encryptString('router-asli'),
        ]);
    }
}
