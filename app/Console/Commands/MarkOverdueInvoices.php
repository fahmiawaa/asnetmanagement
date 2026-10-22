<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mengubah invoice unpaid menjadi overdue jika jatuh tempo sudah terlewati';

    public function handle(): int
    {
        $overdueInvoices = Invoice::with('customer')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<', today())
            ->lazyById(200);

        $updatedInvoices = 0;
        $updatedCustomers = 0;

        foreach ($overdueInvoices as $invoice) {
            if ($invoice->status === 'unpaid') {
                $invoice->update([
                    'status' => 'overdue',
                ]);

                $updatedInvoices++;
            }

            if (
                $invoice->customer &&
                $invoice->customer->status === 'active'
            ) {
                $invoice->customer->update([
                    'status' => 'overdue',
                ]);

                $updatedCustomers++;
            }
        }

        $this->info(
            $updatedInvoices
            .' invoice baru ditandai overdue dan '
            .$updatedCustomers
            .' pelanggan ditandai menunggak.'
        );

        return Command::SUCCESS;
    }
}
