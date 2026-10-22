<?php

namespace App\Console\Commands;

use App\Services\RestoreDrillService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

#[Signature('asnet:restore-drill
    {--backup= : Path file backup .sql.gz yang akan diuji}
    {--strict : Gagal ketika fase restore database dilewati}
    {--keep-database : Pertahankan database sementara hasil restore}')]
#[Description('Menguji backup: verifikasi arsip, restore ke database sementara, dan bandingkan jumlah baris')]
class RestoreDrill extends Command
{
    public function handle(RestoreDrillService $drill): int
    {
        $path = $this->option('backup') ?: $drill->latestArchivePath();

        if (! is_string($path) || $path === '') {
            $this->error('Backup tidak ditemukan. Jalankan asnet:backup atau gunakan --backup=');

            return self::FAILURE;
        }

        $report = $drill->verifyArchive($path, $drill->manifestChecksum($path));

        $this->table(
            ['Pemeriksaan', 'Hasil'],
            [
                ['File', basename($report['path'])],
                ['Ukuran', number_format($report['size']).' byte'],
                ['SHA-256', $report['sha256']],
                ['Terenskripsi', $report['encrypted'] ? 'Ya' : 'Tidak'],
                ['Tabel pada dump', (string) count($report['tables'])],
                ['Baris INSERT', (string) $report['inserts']],
            ]
        );

        if ($report['errors'] !== []) {
            foreach ($report['errors'] as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Fase 1 (verifikasi arsip) lulus.');

        return $this->restorePhase($drill, $report['path']);
    }

    private function restorePhase(RestoreDrillService $drill, string $path): int
    {
        if ((string) config('database.default') !== 'mysql') {
            $this->warn('Fase 2 dilewati: koneksi database default bukan MySQL.');

            return $this->skipResult();
        }

        $binary = (string) config('backup.mysql_binary');
        $mysql = (new ExecutableFinder)->find($binary);

        if ($mysql === null) {
            $this->warn("Fase 2 dilewati: binary mysql '{$binary}' tidak ditemukan.");

            return $this->skipResult();
        }

        $connection = (array) config('database.connections.mysql');
        $temporaryDatabase = 'asnet_drill_'.now()->format('YmdHis').random_int(100, 999);

        try {
            DB::statement("CREATE DATABASE `{$temporaryDatabase}` CHARACTER SET utf8mb4");
        } catch (Throwable $exception) {
            $this->error('Fase 2 gagal membuat database sementara: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line("Database sementara: {$temporaryDatabase}");

        try {
            $this->importArchive($drill, $mysql, $connection, $path, $temporaryDatabase);

            return $this->compareRowCounts($connection, $temporaryDatabase);
        } catch (Throwable $exception) {
            $this->error('Fase 2 gagal: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            try {
                DB::purge('drill');
            } catch (Throwable) {
                // Koneksi drill mungkin belum pernah dibuat.
            }

            if (! $this->option('keep-database')) {
                try {
                    DB::statement("DROP DATABASE IF EXISTS `{$temporaryDatabase}`");
                } catch (Throwable $exception) {
                    $this->warn('Database sementara tidak dapat dihapus: '.$exception->getMessage());
                }
            } else {
                $this->line("Database sementara dipertahankan: {$temporaryDatabase}");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function importArchive(
        RestoreDrillService $drill,
        string $mysql,
        array $connection,
        string $path,
        string $temporaryDatabase,
    ): void {
        $process = new Process([
            $mysql,
            '--host='.(string) $connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.(string) $connection['username'],
            $temporaryDatabase,
        ], base_path(), ['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);
        $process->setTimeout((int) config('backup.timeout_seconds'));

        $readable = $drill->readablePath($path);

        if ($readable['error'] !== null) {
            throw new RuntimeException($readable['error']);
        }

        $stream = gzopen($readable['path'], 'rb');

        if ($stream === false) {
            if ($readable['temporary']) {
                @unlink($readable['path']);
            }

            throw new RuntimeException('Arsip backup tidak dapat dibuka untuk restore.');
        }

        try {
            $process->setInput($stream);
            $process->run();
        } finally {
            gzclose($stream);

            if ($readable['temporary']) {
                @unlink($readable['path']);
            }
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Proses restore gagal.');
        }

        $this->line('Restore ke database sementara berhasil.');
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function compareRowCounts(array $connection, string $temporaryDatabase): int
    {
        config([
            'database.connections.drill' => array_merge($connection, [
                'database' => $temporaryDatabase,
                'prefix' => '',
            ]),
        ]);

        DB::purge('drill');

        $required = array_values(array_filter((array) config('backup.required_tables'), 'is_string'));
        $rows = [];
        $mismatch = [];

        foreach ($required as $table) {
            $source = (int) DB::table($table)->count();
            $restored = (int) DB::connection('drill')->table($table)->count();

            $rows[] = [$table, (string) $source, (string) $restored];

            if ($source !== $restored) {
                $mismatch[] = "Tabel {$table}: sumber {$source} baris, hasil restore {$restored} baris.";
            }
        }

        $this->table(['Tabel', 'Sumber', 'Hasil Restore'], $rows);

        if ($mismatch !== []) {
            foreach ($mismatch as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->info('Fase 2 (restore dan perbandingan jumlah baris) lulus.');

        return self::SUCCESS;
    }

    private function skipResult(): int
    {
        if ($this->option('strict')) {
            $this->error('Fase 2 dilewati dan --strict aktif.');

            return self::FAILURE;
        }

        $this->warn('Drill dianggap lulus untuk fase arsip saja. Jalankan di staging dengan --strict untuk kriteria produksi.');

        return self::SUCCESS;
    }
}
