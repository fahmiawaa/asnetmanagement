<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use App\Services\StagingDataMaskService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('asnet:mask-staging
    {--dry-run : Tampilkan rencana masking tanpa mengubah data}
    {--force : Izinkan dijalankan di luar environment staging}')]
#[Description('Menyamarkan PII, kredensial, dan identitas perangkat agar aman dipindahkan ke staging')]
class MaskStagingData extends Command
{
    public function handle(StagingDataMaskService $mask, AuditLogService $audit): int
    {
        if (app()->environment('production')) {
            $this->error('Masking data tidak boleh dijalankan pada environment production.');

            return self::FAILURE;
        }

        if (! app()->environment(['local', 'staging', 'testing']) && ! $this->option('force')) {
            $this->error('Environment bukan staging. Tambahkan --force untuk memaksa.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->planTable($mask);
            $this->info('Dry-run: tidak ada data yang diubah.');

            return self::SUCCESS;
        }

        $counts = DB::transaction(fn (): array => $mask->mask());

        $this->table(
            ['Tabel', 'Baris Disamarkan'],
            collect($counts)
                ->map(fn (int $count, string $table): array => [$table, $count])
                ->values()
                ->all()
        );

        $audit->record(
            'staging.data_masked',
            null,
            'Data PII dan kredensial disamarkan untuk environment staging.',
            ['tables' => $counts]
        );

        $this->info('Masking selesai. Data siap dipindahkan ke staging.');

        return self::SUCCESS;
    }

    private function planTable(StagingDataMaskService $mask): void
    {
        $counts = $mask->preview();
        $plan = $mask->plan();

        $rows = collect($plan)
            ->map(function (array $columns, string $table) use ($counts): array {
                return [$table, $counts[$table] ?? 0, implode(', ', $columns)];
            })
            ->values()
            ->all();

        $this->table(['Tabel', 'Baris', 'Kolom Disamarkan'], $rows);
    }
}
