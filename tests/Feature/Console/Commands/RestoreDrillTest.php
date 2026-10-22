<?php

namespace Tests\Feature\Console\Commands;

use App\Services\BackupEncryptionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestoreDrillTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'backup.minimum_bytes' => 10,
            'database.default' => 'sqlite',
        ]);
    }

    public function test_valid_archive_passes_and_restore_phase_is_skipped_without_mysql(): void
    {
        $path = $this->archive($this->fullDump());

        $this->artisan('asnet:restore-drill', ['--backup' => $path])
            ->expectsOutputToContain('Fase 1 (verifikasi arsip) lulus.')
            ->assertSuccessful();
    }

    public function test_strict_mode_fails_when_restore_phase_is_skipped(): void
    {
        $path = $this->archive($this->fullDump());

        $this->artisan('asnet:restore-drill', ['--backup' => $path, '--strict' => true])
            ->assertFailed();
    }

    public function test_missing_backup_file_fails(): void
    {
        $this->artisan('asnet:restore-drill', ['--backup' => sys_get_temp_dir().'/tidak-ada.sql.gz'])
            ->assertFailed();
    }

    public function test_checksum_mismatch_fails(): void
    {
        $path = $this->archive($this->fullDump(), 'hash-salah');

        $this->artisan('asnet:restore-drill', ['--backup' => $path])
            ->assertFailed();
    }

    public function test_non_gzip_archive_fails(): void
    {
        $path = $this->archive($this->fullDump());
        file_put_contents($path, "bukan gzip\n");

        $this->artisan('asnet:restore-drill', ['--backup' => $path])
            ->assertFailed();
    }

    public function test_dump_missing_required_tables_fails(): void
    {
        $dump = implode('', array_map(
            fn (string $table): string => "CREATE TABLE `{$table}` (id BIGINT);\n",
            ['users', 'invoices', 'payments', 'ont_devices', 'mikrotik_devices'],
        ));
        $path = $this->archive($dump);

        $this->artisan('asnet:restore-drill', ['--backup' => $path])
            ->assertFailed();
    }

    public function test_latest_archive_is_resolved_from_manifest(): void
    {
        Storage::fake('private');

        $sql = $this->fullDump();
        $fileName = 'asnet-20261002-101010.sql.gz';
        $temporary = tempnam(sys_get_temp_dir(), 'asnet-drill-');
        $gzPath = $temporary.'.sql.gz';
        $this->writeGzip($sql, $gzPath);

        $checksum = (string) hash_file('sha256', $gzPath);

        Storage::disk('private')->put('backups/'.$fileName, (string) file_get_contents($gzPath));
        Storage::disk('private')->put('backups/latest.json', json_encode([
            'file' => $fileName,
            'size' => filesize($gzPath),
            'sha256' => $checksum,
            'created_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        @unlink($temporary);
        @unlink($gzPath);

        $this->artisan('asnet:restore-drill')
            ->expectsOutputToContain('Fase 1 (verifikasi arsip) lulus.')
            ->assertSuccessful();
    }

    public function test_fails_when_no_backup_is_configured(): void
    {
        Storage::fake('private');

        $this->artisan('asnet:restore-drill')->assertFailed();
    }

    public function test_encrypted_archive_passes_with_valid_key(): void
    {
        $path = $this->encryptedArchive($this->fullDump());

        $this->artisan('asnet:restore-drill', ['--backup' => $path])
            ->expectsOutputToContain('Fase 1 (verifikasi arsip) lulus.')
            ->expectsOutputToContain('Terenskripsi')
            ->assertSuccessful();
    }

    public function test_encrypted_archive_fails_without_key(): void
    {
        $path = $this->encryptedArchive($this->fullDump());

        config(['backup.encryption.key' => null]);

        $this->artisan('asnet:restore-drill', ['--backup' => $path])
            ->assertFailed();
    }

    public function test_corrupted_encrypted_archive_fails(): void
    {
        $path = $this->encryptedArchive($this->fullDump());

        $bytes = (string) file_get_contents($path);
        $index = intdiv(strlen($bytes), 2);
        $bytes[$index] = chr(ord($bytes[$index]) ^ 0xFF);
        file_put_contents($path, $bytes);

        $this->artisan('asnet:restore-drill', ['--backup' => $path])
            ->assertFailed();
    }

    private function encryptedArchive(string $sql): string
    {
        config(['backup.encryption.key' => base64_encode(random_bytes(32))]);

        $plainPath = $this->archive($sql);
        $encryptedPath = $plainPath.'.enc';

        app(BackupEncryptionService::class)->encryptFile($plainPath, $encryptedPath);

        @unlink($plainPath);
        @unlink($plainPath.'.sha256');

        file_put_contents(
            $encryptedPath.'.sha256',
            hash_file('sha256', $encryptedPath).'  '.basename($encryptedPath).PHP_EOL
        );

        return $encryptedPath;
    }

    private function fullDump(): string
    {
        $tables = (array) config('backup.required_tables');
        $sql = "-- ASNet restore drill\n";

        foreach ($tables as $table) {
            $sql .= "CREATE TABLE `{$table}` (id BIGINT);\n";
            $sql .= "INSERT INTO `{$table}` VALUES (1);\n";
        }

        return $sql;
    }

    private function archive(string $sql, ?string $sidecarHash = null): string
    {
        $directory = sys_get_temp_dir().'/asnet-drill-'.uniqid();
        mkdir($directory, 0777, true);

        $path = $directory.'/backup.sql.gz';
        $this->writeGzip($sql, $path);

        $hash = $sidecarHash ?? (string) hash_file('sha256', $path);
        file_put_contents($path.'.sha256', $hash.'  backup.sql.gz'.PHP_EOL);

        return $path;
    }

    private function writeGzip(string $sql, string $destination): void
    {
        $handle = gzopen($destination, 'wb9');
        $this->assertNotFalse($handle);
        gzwrite($handle, $sql);
        gzclose($handle);
    }
}
