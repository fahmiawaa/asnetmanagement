<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\WhatsAppLinkService;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate-monthly';

    protected $description = 'Membuat invoice berulang berdasarkan tanggal pemasangan';

    public function handle(WhatsAppLinkService $whatsapp): int
    {
        $today = today();
        $createdCount = 0;
        $skippedCount = 0;

        $customers = Customer::with([
            'internetPackage:id,price',
            'invoices' => fn ($query) => $query
                ->select(['id', 'customer_id'])
                ->where('status', 'overdue'),
        ])
            ->where('status', 'active')
            ->whereNotNull('internet_package_id')
            ->lazyById(200);

        foreach ($customers as $customer) {
            if (! $customer->internetPackage) {
                $skippedCount++;

                continue;
            }

            $installationDay = $customer->installation_date?->day
                ?? (int) $customer->due_day;
            $billingDay = min($installationDay ?: 1, $today->daysInMonth);

            if ($today->day !== $billingDay) {
                $skippedCount++;

                continue;
            }

            if ($customer->invoices->isNotEmpty()) {
                $skippedCount++;

                continue;
            }

            $periodStart = $today->copy()->startOfDay();
            $periodEnd = $periodStart->copy()->addMonth()->subDay();
            $invoiceNumber = 'INV-'.$periodStart->format('Ymd').'-'.$customer->customer_code;

            $invoiceExists = Invoice::query()
                ->where('customer_id', $customer->id)
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->exists();

            if ($invoiceExists) {
                $skippedCount++;

                continue;
            }

            $message = 'Invoice internet '.$invoiceNumber.' untuk '.$customer->name.' sudah terbit.';
            $whatsappUrl = $whatsapp->link(
                $customer->whatsapp_number,
                $message
            );

            try {
                Invoice::create([
                    'customer_id' => $customer->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'invoice_number' => $invoiceNumber,
                    'due_date' => $periodStart->toDateString(),
                    'total_amount' => $customer->internetPackage->price,
                    'status' => 'unpaid',
                    'paid_at' => null,
                    'notes' => 'Invoice berulang dibuat berdasarkan tanggal pemasangan.'
                        .($whatsappUrl ? ' Link WhatsApp: '.$whatsappUrl : ''),
                ]);

                $createdCount++;
            } catch (UniqueConstraintViolationException) {
                $skippedCount++;
            }
        }

        $this->info("{$createdCount} invoice dibuat dan {$skippedCount} customer dilewati.");

        return self::SUCCESS;
    }
}
