<?php

namespace App\Console\Commands;

use App\Models\OntStatusHistory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('genieacs:prune-history
    {--days= : Hari retensi yang dipakai, default dari konfigurasi (30-90)}
    {--limit= : Jumlah baris yang dihapus per batch}')]
#[Description('Hapus riwayat status ONT yang melewati batas retensi')]
class PruneOntStatusHistory extends Command
{
    private const MIN_RETENTION_DAYS = 30;

    private const MAX_RETENTION_DAYS = 90;

    private const MAX_BATCHES = 200;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = $this->resolveRetentionDays();
        $batch = $this->resolveBatchSize();
        $cutoff = now()->subDays($days);

        $total = 0;
        $batches = 0;

        do {
            $ids = OntStatusHistory::query()
                ->where('observed_at', '<', $cutoff)
                ->limit($batch)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $total += OntStatusHistory::query()->whereIn('id', $ids)->delete();
            $batches++;
        } while ($ids->count() === $batch && $batches < self::MAX_BATCHES);

        $this->info(
            "Riwayat status ONT lebih lama dari {$days} hari dihapus: {$total} baris."
        );

        if ($ids->count() === $batch) {
            $this->warn('Masih ada sisa riwayat; jalankan kembali perintah ini.');
        }

        return self::SUCCESS;
    }

    private function resolveRetentionDays(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('genieacs.history_retention_days');

        return max(self::MIN_RETENTION_DAYS, min(self::MAX_RETENTION_DAYS, $days));
    }

    private function resolveBatchSize(): int
    {
        $batch = $this->option('limit') !== null
            ? (int) $this->option('limit')
            : (int) config('genieacs.history_prune_batch');

        return max(1, $batch);
    }
}
