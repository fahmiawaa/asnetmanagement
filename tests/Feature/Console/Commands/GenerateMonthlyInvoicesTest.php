<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GenerateMonthlyInvoicesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_generates_invoices_for_700_customers_in_bounded_batches(): void
    {
        $this->travelTo('2026-09-26 00:10:00');
        $internetPackage = InternetPackage::factory()->create([
            'price' => 175000,
        ]);
        Customer::factory()
            ->count(700)
            ->for($internetPackage)
            ->create([
                'installation_date' => '2026-08-26',
                'due_day' => 26,
                'status' => 'active',
            ]);

        $this->artisan('invoices:generate-monthly')
            ->expectsOutput('700 invoice dibuat dan 0 customer dilewati.')
            ->assertSuccessful();

        $this->assertSame(700, Invoice::query()->count());
        $this->assertSame(
            700,
            Invoice::query()
                ->whereDate('period_start', '2026-09-26')
                ->whereDate('period_end', '2026-10-25')
                ->count()
        );
    }

    public function test_does_not_create_duplicate_invoice_when_command_runs_again(): void
    {
        $this->travelTo('2026-09-26 00:10:00');
        $customer = Customer::factory()->create([
            'installation_date' => '2026-08-26',
            'due_day' => 26,
        ]);

        $this->artisan('invoices:generate-monthly')->assertSuccessful();
        $this->artisan('invoices:generate-monthly')
            ->expectsOutput('0 invoice dibuat dan 1 customer dilewati.')
            ->assertSuccessful();

        $this->assertSame(
            1,
            Invoice::query()
                ->where('customer_id', $customer->id)
                ->count()
        );
    }

    public function test_uses_last_day_of_month_for_customer_billed_on_day_31(): void
    {
        $this->travelTo('2026-04-30 00:10:00');
        $customer = Customer::factory()->create([
            'installation_date' => '2026-03-31',
            'due_day' => 31,
        ]);

        $this->artisan('invoices:generate-monthly')->assertSuccessful();

        $invoice = Invoice::query()
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->assertSame('2026-04-30', $invoice->period_start->toDateString());
        $this->assertSame('2026-05-29', $invoice->period_end->toDateString());
        $this->assertSame('2026-04-30', $invoice->due_date->toDateString());
        $this->assertSame('unpaid', $invoice->status);
    }
}
