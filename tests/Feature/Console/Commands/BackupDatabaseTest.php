<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    public function test_backup_is_compressed_checksummed_and_copied_offsite(): void
    {
        Storage::fake('private');
        Storage::fake('offsite');

        $dumpBinary = tempnam(sys_get_temp_dir(), 'fake-mysqldump-');

        if (PHP_OS_FAMILY === 'Windows') {
            $original = $dumpBinary;
            $dumpBinary .= '.bat';
            @unlink($original);
            file_put_contents($dumpBinary, <<<'BAT'
@echo off
echo -- ASNet test dump
echo CREATE TABLE customers (id BIGINT);
echo INSERT INTO customers VALUES (1);
BAT);
        } else {
            file_put_contents($dumpBinary, <<<'SH'
#!/bin/sh
printf '%s\n' '-- ASNet test dump' 'CREATE TABLE customers (id BIGINT);' 'INSERT INTO customers VALUES (1);'
SH);
            chmod($dumpBinary, 0755);
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'username' => 'asnet',
                'password' => 'secret',
                'database' => 'asnet',
            ],
            'backup.mysqldump_binary' => $dumpBinary,
            'backup.minimum_bytes' => 10,
            'backup.local_disk' => 'private',
            'backup.local_path' => 'backups',
            'backup.offsite_enabled' => true,
            'backup.offsite_disk' => 'offsite',
            'backup.offsite_path' => 'remote',
        ]);

        try {
            $this->artisan('asnet:backup')->assertSuccessful();
        } finally {
            @unlink($dumpBinary);
        }

        $localFiles = Storage::disk('private')->allFiles('backups');
        $archive = collect($localFiles)->first(fn (string $file) => str_ends_with($file, '.sql.gz'));

        $this->assertNotNull($archive);
        Storage::disk('private')->assertExists($archive.'.sha256');
        Storage::disk('private')->assertExists('backups/latest.json');
        Storage::disk('offsite')->assertExists('remote/'.basename($archive));
        Storage::disk('offsite')->assertExists('remote/'.basename($archive).'.sha256');
        Storage::disk('offsite')->assertExists('remote/latest.json');

        $manifest = json_decode(
            Storage::disk('private')->get('backups/latest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $this->assertTrue($manifest['offsite_uploaded']);
        $this->assertSame(
            $manifest['sha256'],
            hash('sha256', Storage::disk('private')->get($archive))
        );
        $decoded = gzdecode(Storage::disk('private')->get($archive));
        $this->assertNotEmpty($decoded);
        $this->assertStringContainsString('CREATE TABLE customers', $decoded);
    }
}
