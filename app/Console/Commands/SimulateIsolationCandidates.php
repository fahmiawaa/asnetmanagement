<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\IsolationLog;
use Illuminate\Console\Command;

class SimulateIsolationCandidates extends Command
{
    protected $signature = 'isolation:simulate';

    protected $description = 'Mencatat kandidat auto isolir tanpa mengubah pelanggan atau MikroTik';

    public function handle(): int
    {
        $gracePeriodDays = max(0, (int) config('mikrotik.grace_period_days', 7));

        $eligibleDate = today()->subDays($gracePeriodDays);

        $overdueInvoices = Invoice::with('customer.pppoeAccount')
            ->where('status', 'overdue')
            ->whereDate('due_date', '<=', $eligibleDate)
            ->lazyById(200);

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($overdueInvoices as $invoice) {
            $customer = $invoice->customer;

            if (! $customer) {
                $skippedCount++;

                continue;
            }

            if (in_array($customer->status, ['isolated', 'inactive'], true)) {
                $skippedCount++;

                continue;
            }

            if (! $customer->pppoeAccount || ! $customer->pppoeAccount->is_present) {
                $skippedCount++;

                continue;
            }

            $log = IsolationLog::firstOrCreate(
                [
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'action' => 'isolate',
                ],
                [
                    'reason' => "Invoice overdue dan melewati masa toleransi {$gracePeriodDays} hari.",
                    'old_status' => $customer->status,
                    'new_status' => 'isolated',
                    'execution_mode' => 'simulation',
                    'approval_status' => config('mikrotik.auto_isolation_enabled')
                        && config('mikrotik.allow_real_actions')
                        ? 'approved'
                        : 'pending',
                    'approved_by' => null,
                    'approved_at' => null,
                    'executed_by' => null,
                    'executed_at' => null,
                    'notes' => 'Kandidat auto-isolasi. Tidak mengubah status pelanggan atau MikroTik sampai preflight dan eksekusi live berhasil.',
                ]
            );

            $log->wasRecentlyCreated
                ? $createdCount++
                : $skippedCount++;
        }

        $this->info(
            $createdCount
            .' kandidat isolir dicatat dan '
            .$skippedCount
            .' data dilewati.'
        );

        return Command::SUCCESS;
    }
}
