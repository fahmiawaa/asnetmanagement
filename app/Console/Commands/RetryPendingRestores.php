<?php

namespace App\Console\Commands;

use App\Models\IsolationLog;
use App\Services\RestoreCustomerService;
use Illuminate\Console\Command;

class RetryPendingRestores extends Command
{
    protected $signature = 'billing:retry-pending-restores';

    protected $description = 'Mencoba kembali restore customer yang masih pending';

    public function handle(RestoreCustomerService $restoreService): int
    {
        if (! config('mikrotik.allow_real_actions')) {
            $this->warn('Restore nyata masih dikunci oleh kill switch.');

            return self::SUCCESS;
        }

        $limit = (int) config('mikrotik.restore_batch_size', 25);
        $logs = IsolationLog::with('customer.pppoeAccount.mikrotikDevice')
            ->where('action', 'restore')
            ->where('approval_status', 'pending')
            ->where(function ($query): void {
                $query
                    ->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $failedCount = 0;

        foreach ($logs as $log) {
            $log->update([
                'attempt_count' => $log->attempt_count + 1,
                'last_attempt_at' => now(),
                'next_retry_at' => null,
                'last_error' => null,
            ]);
            $result = $restoreService->execute($log);
            $this->line($result['message']);

            if ($result['success']) {
                continue;
            }

            $failedCount++;
            $log->refresh();
            $maxAttempts = (int) config('mikrotik.restore_max_attempts', 10);
            $hasAttemptsRemaining = $log->attempt_count < $maxAttempts;
            $baseMinutes = (int) config(
                'mikrotik.restore_retry_base_minutes',
                2
            );
            $backoffMinutes = min(
                60,
                $baseMinutes * (2 ** max(0, $log->attempt_count - 1))
            );
            $log->update([
                'approval_status' => $hasAttemptsRemaining
                    ? 'pending'
                    : 'failed',
                'next_retry_at' => $hasAttemptsRemaining
                    ? now()->addMinutes($backoffMinutes)
                    : null,
                'last_error' => $result['message'],
            ]);
        }

        $this->line("Restore diproses: {$logs->count()} / batas batch {$limit}");

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
