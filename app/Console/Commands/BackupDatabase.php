<?php

namespace App\Console\Commands;

use App\Services\BackupEncryptionService;
use App\Services\OperationalAlertService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'asnet:backup
        {--keep= : Override jumlah hari backup lokal}
        {--local-only : Lewati upload backup di luar server}';

    protected $description = 'Membuat backup MySQL terkompresi, checksum, dan salinan di luar server';

    public function handle(
        OperationalAlertService $alerts,
        BackupEncryptionService $encryption,
    ): int {
        if (config('database.default') !== 'mysql') {
            $this->error('Command backup saat ini hanya mendukung koneksi MySQL.');

            return self::FAILURE;
        }

        $rawPath = tempnam(sys_get_temp_dir(), 'asnet-backup-');
        $gzipPath = $rawPath.'.sql.gz';
        $encryptedPath = $gzipPath.'.enc';

        if ($rawPath === false) {
            $this->error('Direktori temporary tidak dapat digunakan.');

            return self::FAILURE;
        }

        try {
            $this->dumpDatabase($rawPath);
            $this->compress($rawPath, $gzipPath);

            $artifactPath = $gzipPath;
            $encrypted = false;

            if ($encryption->isEnabled()) {
                $encryption->encryptFile($gzipPath, $encryptedPath);
                $artifactPath = $encryptedPath;
                $encrypted = true;
            }

            $size = filesize($artifactPath);

            if ($size === false || $size < (int) config('backup.minimum_bytes')) {
                throw new RuntimeException("Ukuran backup {$size} byte berada di bawah batas aman.");
            }

            $checksum = hash_file('sha256', $artifactPath);
            $fileName = 'asnet-'.now()->format('Ymd-His').'.sql.gz'.($encrypted ? '.enc' : '');
            $localPath = trim((string) config('backup.local_path'), '/');
            $localDisk = Storage::disk((string) config('backup.local_disk'));

            $this->storeAndVerify($localDisk, $localPath, $fileName, $artifactPath, $checksum);

            $offsiteUploaded = false;
            if (config('backup.offsite_enabled') && ! $this->option('local-only')) {
                $this->storeAndVerify(
                    Storage::disk((string) config('backup.offsite_disk')),
                    trim((string) config('backup.offsite_path'), '/'),
                    $fileName,
                    $artifactPath,
                    $checksum
                );
                $offsiteUploaded = true;
            }

            $manifest = [
                'file' => $fileName,
                'size' => $size,
                'sha256' => $checksum,
                'encrypted' => $encrypted,
                'created_at' => now()->toIso8601String(),
                'offsite_uploaded' => $offsiteUploaded,
            ];

            $localDisk->put(
                $localPath.'/latest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            );

            if ($offsiteUploaded) {
                Storage::disk((string) config('backup.offsite_disk'))->put(
                    trim((string) config('backup.offsite_path'), '/').'/latest.json',
                    json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
                );
            }

            $this->prune(
                $localDisk,
                $localPath,
                max(1, (int) ($this->option('keep') ?: config('backup.local_retention_days')))
            );

            if ($offsiteUploaded) {
                $this->prune(
                    Storage::disk((string) config('backup.offsite_disk')),
                    trim((string) config('backup.offsite_path'), '/'),
                    max(1, (int) config('backup.offsite_retention_days'))
                );
            }

            $this->info("Backup berhasil: {$fileName}");
            $this->line("SHA-256: {$checksum}");
            $this->line('Enkripsi: '.($encrypted ? 'aktif' : 'nonaktif'));
            $this->line('Offsite: '.($offsiteUploaded ? 'terverifikasi' : 'dinonaktifkan/dilewati'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Backup database gagal: '.$exception->getMessage());
            $alerts->send(
                'backup.failed',
                'Backup database gagal',
                $exception->getMessage(),
                ['command' => 'asnet:backup']
            );

            return self::FAILURE;
        } finally {
            @unlink($rawPath);
            @unlink($gzipPath);
            @unlink($encryptedPath);
        }
    }

    private function dumpDatabase(string $path): void
    {
        $connection = config('database.connections.mysql');
        $process = new Process([
            (string) config('backup.mysqldump_binary'),
            '--host='.(string) $connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.(string) $connection['username'],
            '--single-transaction',
            '--quick',
            '--routines',
            '--events',
            '--no-tablespaces',
            (string) $connection['database'],
        ], base_path(), [
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ]);
        $process->setTimeout((int) config('backup.timeout_seconds'));

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('File temporary backup tidak dapat ditulis.');
        }

        try {
            $process->run(function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump gagal.');
        }
    }

    private function compress(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb9');

        if ($input === false || $output === false) {
            throw new RuntimeException('File backup tidak dapat dikompres.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 1024);

                if ($chunk === false || gzwrite($output, $chunk) === false) {
                    throw new RuntimeException('Kompresi backup gagal.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }

    private function storeAndVerify(
        Filesystem $disk,
        string $directory,
        string $fileName,
        string $source,
        string $checksum,
    ): void {
        $path = $directory.'/'.$fileName;
        $stream = fopen($source, 'rb');

        if ($stream === false || ! $disk->put($path, $stream)) {
            throw new RuntimeException("Upload {$path} gagal.");
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        $disk->put($path.'.sha256', $checksum.'  '.$fileName.PHP_EOL);

        if (! $disk->exists($path) || $disk->size($path) !== filesize($source)) {
            throw new RuntimeException("Verifikasi ukuran {$path} gagal.");
        }

        $remoteStream = $disk->readStream($path);
        $context = hash_init('sha256');

        if ($remoteStream === false || hash_update_stream($context, $remoteStream) === false) {
            throw new RuntimeException("Verifikasi checksum {$path} gagal.");
        }

        fclose($remoteStream);

        if (! hash_equals($checksum, hash_final($context))) {
            throw new RuntimeException("Checksum {$path} tidak cocok.");
        }
    }

    private function prune(Filesystem $disk, string $directory, int $retentionDays): void
    {
        $cutoff = now()->subDays($retentionDays)->getTimestamp();

        foreach ($disk->files($directory) as $file) {
            if (! str_ends_with($file, '.sql.gz') && ! str_ends_with($file, '.sql.gz.enc')) {
                continue;
            }

            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete([$file, $file.'.sha256']);
            }
        }
    }
}
