<?php

namespace App\Console\Commands;

use App\Models\IsolationLog;
use App\Services\MikrotikConnectionService;
use Illuminate\Console\Command;
use Throwable;

class SimulateRestoreCandidates extends Command
{
    protected $signature = 'isolation:simulate-restore';

    protected $description =
        'Mencatat kandidat restore tanpa mengubah pelanggan atau MikroTik';

    public function handle(
        MikrotikConnectionService $connectionService
    ): int {
        $isolationLogs = IsolationLog::with([
            'customer.pppoeAccount.mikrotikDevice',
            'invoice',
        ])
            ->where('action', 'isolate')
            ->where('execution_mode', 'real')
            ->where('approval_status', 'executed')
            ->where('new_status', 'isolated')
            ->whereHas('invoice', function ($query) {
                $query->where('status', 'paid');
            })
            ->get();

        $createdCount = 0;
        $skippedCount = 0;
        $liveAccountsCache = [];

        foreach ($isolationLogs as $isolationLog) {
            $customer = $isolationLog->customer;
            $pppoeAccount = $customer?->pppoeAccount;
            $device = $pppoeAccount?->mikrotikDevice;

            if (! $customer || ! $pppoeAccount || ! $device) {
                $skippedCount++;

                continue;
            }

            $hasOtherOverdueInvoice = $customer
                ->invoices()
                ->where('status', 'overdue')
                ->exists();

            if ($hasOtherOverdueInvoice) {
                $skippedCount++;

                continue;
            }

            $deviceId = $device->id;

            if (! array_key_exists($deviceId, $liveAccountsCache)) {
                try {
                    $liveAccountsCache[$deviceId] =
                        $connectionService->getPppoeSecrets($device);
                } catch (Throwable $exception) {
                    report($exception);

                    $liveAccountsCache[$deviceId] = null;
                }
            }

            $liveAccounts = $liveAccountsCache[$deviceId];

            if ($liveAccounts === null) {
                $skippedCount++;

                continue;
            }

            $liveAccount = collect($liveAccounts)
                ->firstWhere('name', $pppoeAccount->username);

            if (! $liveAccount) {
                $skippedCount++;

                continue;
            }

            $disabledValue = strtolower(
                (string) ($liveAccount['disabled'] ?? 'false')
            );

            $isDisabled = in_array(
                $disabledValue,
                ['true', 'yes', '1'],
                true
            );

            if (! $isDisabled) {
                $this->line(
                    "Lewati {$customer->customer_code}: "
                    .'akun PPPoE masih active.'
                );

                $skippedCount++;

                continue;
            }

            $alreadyLogged = IsolationLog::where(
                'customer_id',
                $isolationLog->customer_id
            )
                ->where(
                    'invoice_id',
                    $isolationLog->invoice_id
                )
                ->where('action', 'restore')
                ->whereIn('approval_status', [
                    'pending',
                    'approved',
                    'executed',
                ])
                ->exists();

            if ($alreadyLogged) {
                $skippedCount++;

                continue;
            }

            IsolationLog::create([
                'customer_id' => $isolationLog->customer_id,
                'invoice_id' => $isolationLog->invoice_id,
                'action' => 'restore',
                'reason' => 'Invoice sudah dibayar dan diverifikasi.',
                'old_status' => 'isolated',
                'new_status' => 'active',
                'execution_mode' => 'simulation',
                'approval_status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
                'executed_by' => null,
                'executed_at' => null,
                'notes' => 'Akun PPPoE terdeteksi disabled. '
                    .'Menunggu approval restore.',
            ]);

            $createdCount++;
        }

        $this->info(
            $createdCount
            .' kandidat restore dicatat dan '
            .$skippedCount
            .' data dilewati.'
        );

        return self::SUCCESS;
    }
}
