<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigratePaymentProofs extends Command
{
    protected $signature = 'files:migrate-payment-proofs
                            {--confirm= : Wajib MIGRATE-PAYMENT-PROOFS}';

    protected $description = 'Memindahkan bukti pembayaran dari public ke private storage';

    public function handle(): int
    {
        if ($this->option('confirm') !== 'MIGRATE-PAYMENT-PROOFS') {
            $this->error(
                'Gunakan --confirm=MIGRATE-PAYMENT-PROOFS untuk melanjutkan.'
            );

            return self::FAILURE;
        }

        $public = Storage::disk('public');
        $private = Storage::disk('private');
        $migrated = 0;
        $missing = 0;
        $failed = 0;

        Payment::query()
            ->whereNotNull('proof_path')
            ->chunkById(100, function ($payments) use (
                $public,
                $private,
                &$migrated,
                &$missing,
                &$failed
            ): void {
                foreach ($payments as $payment) {
                    $path = (string) $payment->proof_path;

                    if ($private->exists($path)) {
                        continue;
                    }

                    if (! $public->exists($path)) {
                        $missing++;
                        $this->warn(
                            "Bukti tidak ditemukan untuk payment #{$payment->id}."
                        );

                        continue;
                    }

                    try {
                        $private->put($path, $public->get($path));
                        $public->delete($path);
                        $migrated++;
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                        $this->error(
                            "Gagal memindahkan bukti payment #{$payment->id}."
                        );
                    }
                }
            });

        $this->info("Berhasil dipindahkan: {$migrated}");
        $this->warn("Tidak ditemukan: {$missing}");

        if ($failed > 0) {
            $this->error("Gagal dipindahkan: {$failed}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
