<?php

namespace Tests\Feature\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\IsolationLog;
use App\Models\MikrotikDevice;
use App\Models\PppoeAccount;
use App\Services\AuditLogService;
use App\Services\MikrotikConnectionService;
use App\Services\RestoreCustomerService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RestoreCustomerServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_does_not_restore_customer_with_another_overdue_invoice(): void
    {
        config(['mikrotik.allow_real_actions' => true]);
        $device = MikrotikDevice::query()->create([
            'name' => 'Router Desa A',
            'host' => '10.10.10.1',
            'api_port' => 8728,
            'use_ssl' => false,
            'username' => 'billing-automation',
            'encrypted_password' => 'encrypted-test-value',
        ]);
        $pppoeAccount = PppoeAccount::query()->create([
            'mikrotik_device_id' => $device->id,
            'username' => 'pelanggan-0001',
            'disabled' => true,
            'is_present' => true,
        ]);
        $customer = Customer::factory()->create([
            'pppoe_account_id' => $pppoeAccount->id,
            'status' => 'isolated',
        ]);
        $paidInvoice = Invoice::factory()
            ->for($customer)
            ->create([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        Invoice::factory()
            ->for($customer)
            ->create([
                'invoice_number' => 'INV-OTHER-OVERDUE',
                'period_start' => today()->subMonth(),
                'period_end' => today()->subDay(),
                'status' => 'overdue',
            ]);
        $restoreLog = IsolationLog::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $paidInvoice->id,
            'action' => 'restore',
            'old_status' => 'isolated',
            'new_status' => 'active',
            'execution_mode' => 'simulation',
            'approval_status' => 'pending',
        ]);
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('getPppoeSecrets');
        $auditLog = $this->mock(AuditLogService::class);
        $auditLog->shouldReceive('record')->once();
        $service = new RestoreCustomerService(
            $connectionService,
            $auditLog
        );

        $result = $service->execute($restoreLog);

        $this->assertFalse($result['success']);
        $this->assertSame('isolated', $customer->refresh()->status);
        $this->assertTrue($pppoeAccount->refresh()->disabled);
        $this->assertSame('pending', $restoreLog->refresh()->approval_status);
    }
}
