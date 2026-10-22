<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\IsolationLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PaymentIsolationRaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_payment_verification_waits_while_auto_isolation_is_processing(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'active' => true,
        ]);
        $customer = Customer::factory()->create([
            'status' => 'overdue',
        ]);
        $invoice = Invoice::factory()
            ->for($customer)
            ->create([
                'status' => 'overdue',
                'total_amount' => 150000,
            ]);
        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_date' => today(),
            'amount' => 150000,
            'method' => 'cash',
            'status' => 'pending',
        ]);
        IsolationLog::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'action' => 'isolate',
            'old_status' => 'overdue',
            'new_status' => 'isolated',
            'execution_mode' => 'simulation',
            'approval_status' => 'processing',
            'attempt_count' => 1,
            'last_attempt_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('payments.verify', $payment))
            ->assertSessionHasErrors('payment');

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame('overdue', $invoice->refresh()->status);
        $this->assertSame('overdue', $customer->refresh()->status);
    }
}
