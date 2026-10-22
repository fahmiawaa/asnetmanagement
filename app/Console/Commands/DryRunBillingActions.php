<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class DryRunBillingActions extends Command
{
    protected $signature = 'billing:dry-run';

    protected $description = 'Menampilkan kandidat tindakan billing tanpa mengubah MikroTik';

    public function handle(): int
    {
        $customers = Customer::with([
            'pppoeAccount.mikrotikDevice',
            'invoices' => function ($query) {
                $query->where('status', 'overdue');
            },
        ])
            ->whereHas('invoices', function ($query) {
                $query->where('status', 'overdue');
            })
            ->get();

        if ($customers->isEmpty()) {
            $this->info(
                'Tidak ada customer dengan invoice overdue.'
            );

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($customers as $customer) {
            $pppoeAccount = $customer->pppoeAccount;

            $overdueCount = $customer->invoices->count();

            $overdueAmount = $customer->invoices
                ->sum('total_amount');

            if (! $pppoeAccount) {
                $action = 'SKIP - Belum mapping PPPoE';
                $username = '-';
                $profile = '-';
                $pppoeStatus = '-';
            } elseif ($customer->status === 'isolated') {
                $action = 'SKIP - Customer sudah isolated';
                $username = $pppoeAccount->username;
                $profile = $pppoeAccount->profile ?? '-';
                $pppoeStatus = $pppoeAccount->disabled
                    ? 'Disabled'
                    : 'Active';
            } elseif ($pppoeAccount->disabled) {
                $action = 'SKIP - Akun PPPoE sudah disabled';
                $username = $pppoeAccount->username;
                $profile = $pppoeAccount->profile ?? '-';
                $pppoeStatus = 'Disabled';
            } else {
                $action = 'KANDIDAT ISOLIR';
                $username = $pppoeAccount->username;
                $profile = $pppoeAccount->profile ?? '-';
                $pppoeStatus = 'Active';
            }

            $rows[] = [
                $customer->customer_code,
                $customer->name,
                $customer->status,
                $overdueCount,
                'Rp '.number_format(
                    (float) $overdueAmount,
                    0,
                    ',',
                    '.'
                ),
                $username,
                $profile,
                $pppoeStatus,
                $action,
            ];
        }

        $this->table(
            [
                'Kode',
                'Customer',
                'Status',
                'Jml Overdue',
                'Total Overdue',
                'Username PPPoE',
                'Profile',
                'Status PPPoE',
                'Tindakan',
            ],
            $rows
        );

        $this->newLine();

        $this->info(
            'DRY-RUN selesai. Tidak ada perubahan pada MikroTik atau database.'
        );

        return self::SUCCESS;
    }
}
