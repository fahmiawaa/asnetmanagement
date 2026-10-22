<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\IsolationLog;
use App\Services\RestoreCustomerService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RetryPendingRestoresTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_processes_at_most_25_pending_restores_per_run(): void
    {
        config([
            'mikrotik.allow_real_actions' => true,
            'mikrotik.restore_batch_size' => 25,
        ]);
        collect(range(1, 30))->each(function (int $number): void {
            $customer = Customer::factory()->create([
                'customer_code' => 'RESTORE-'.str_pad(
                    (string) $number,
                    4,
                    '0',
                    STR_PAD_LEFT
                ),
                'status' => 'isolated',
            ]);
            $invoice = Invoice::factory()
                ->for($customer)
                ->create([
                    'invoice_number' => 'INV-RESTORE-'.str_pad(
                        (string) $number,
                        4,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            IsolationLog::query()->create([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'action' => 'restore',
                'old_status' => 'isolated',
                'new_status' => 'active',
                'execution_mode' => 'simulation',
                'approval_status' => 'pending',
            ]);
        });
        $restoreService = $this->mock(RestoreCustomerService::class);
        $restoreService->shouldReceive('execute')
            ->times(25)
            ->andReturn([
                'success' => true,
                'message' => 'Restore berhasil.',
                'whatsapp_url' => null,
            ]);

        $this->artisan('billing:retry-pending-restores')
            ->assertSuccessful();

        $this->assertSame(
            25,
            IsolationLog::query()
                ->where('action', 'restore')
                ->where('attempt_count', 1)
                ->count()
        );
        $this->assertSame(
            5,
            IsolationLog::query()
                ->where('action', 'restore')
                ->where('attempt_count', 0)
                ->count()
        );
    }
}
