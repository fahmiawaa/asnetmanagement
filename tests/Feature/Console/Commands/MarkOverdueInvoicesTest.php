<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MarkOverdueInvoicesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_marks_due_invoice_and_active_customer_as_overdue(): void
    {
        $this->travelTo('2026-09-26 00:05:00');
        $customer = Customer::factory()->create([
            'status' => 'active',
        ]);
        $invoice = Invoice::factory()
            ->for($customer)
            ->create([
                'due_date' => '2026-09-25',
                'status' => 'unpaid',
            ]);

        $this->artisan('invoices:mark-overdue')
            ->expectsOutput('1 invoice baru ditandai overdue dan 1 pelanggan ditandai menunggak.')
            ->assertSuccessful();

        $this->assertSame('overdue', $invoice->refresh()->status);
        $this->assertSame('overdue', $customer->refresh()->status);
    }
}
