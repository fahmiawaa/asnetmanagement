<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Throwable;

class RestoreDrillService
{
    public function __construct(private readonly BackupEncryptionService $encryption) {}

    /**
     * Verifikasi arsip backup tanpa menyentuh database: keberadaan file, ukuran,
     * checksum, validitas gzip, inventaris tabel, dan tabel wajib.
     *
     * @return array{
     *     path: string,
     *     size: int,
     *     sha256: string,
     *     encrypted: bool,
     *     tables: array<int, string>,
     *     inserts: int,
     *     errors: array<int, string>
     * }
     */
    public function verifyArchive(string $path, ?string $expectedSha256 = null): array
    {
        $errors = [];
        $tables = [];
        $inserts = 0;
        $size = 0;
        $sha256 = '';
        $encrypted = false;

        if (! is_file($path) || ! is_readable($path)) {
            $errors[] = 'File backup tidak ditemukan atau tidak terbaca.';

            return $this->report($path, 0, '', false, [], 0, $errors);
        }

        $size = (int) filesize($path);
        $sha256 = (string) hash_file('sha256', $path);
        $encrypted = $this->encryption->isEncrypted($path);

        if ($size < max(1, (int) config('backup.minimum_bytes'))) {
            $errors[] = "Ukuran backup {$size} byte di bawah batas aman "
                .((int) config('backup.minimum_bytes')).' byte.';
        }

        if ($expectedSha256 !== null && $expectedSha256 !== ''
            && ! hash_equals($expectedSha256, $sha256)) {
            $errors[] = 'Checksum backup tidak cocok dengan manifest.';
        }

        $sidecar = $path.'.sha256';
        if (is_file($sidecar)) {
            $sidecarHash = strtok(trim((string) file_get_contents($sidecar)), ' ') ?: '';

            if ($sidecarHash === '' || ! hash_equals($sidecarHash, $sha256)) {
                $errors[] = 'Checksum backup tidak cocok dengan berkas .sha256.';
            }
        }

        $readable = $this->readablePath($path);

        if ($readable['error'] !== null) {
            $errors[] = $readable['error'];
        } else {
            try {
                [$tables, $inserts, $scanErrors] = $this->scan($readable['path']);
                $errors = [...$errors, ...$scanErrors];
            } finally {
                if ($readable['temporary']) {
                    @unlink($readable['path']);
                }
            }
        }

        $required = array_values(array_filter((array) config('backup.required_tables', []), 'is_string'));
        $missing = array_diff($required, $tables);

        if ($missing !== []) {
            $errors[] = 'Tabel wajib tidak ada pada dump: '.implode(', ', $missing).'.';
        }

        if ($tables === []) {
            $errors[] = 'Arsip backup tidak berisi definisi tabel.';
        }

        return $this->report($path, $size, $sha256, $encrypted, $tables, $inserts, $errors);
    }

    /**
     * Path yang dapat dibaca langsung sebagai gzip; arsip terenkripsi
     * didekripsi ke file temporary yang harus dibersihkan pemanggil.
     *
     * @return array{path: string, temporary: bool, error: ?string}
     */
    public function readablePath(string $path): array
    {
        if (! $this->encryption->isEncrypted($path)) {
            return ['path' => $path, 'temporary' => false, 'error' => null];
        }

        $destination = tempnam(sys_get_temp_dir(), 'asnet-drill-');

        if ($destination === false) {
            return [
                'path' => '',
                'temporary' => false,
                'error' => 'Dekripsi gagal: direktori temporary tidak dapat digunakan.',
            ];
        }

        $destination .= '.sql.gz';

        try {
            $this->encryption->decryptFile($path, $destination);
        } catch (Throwable $exception) {
            @unlink($destination);

            return [
                'path' => '',
                'temporary' => false,
                'error' => 'Arsip terenkripsi tidak dapat didekripsi: '.$exception->getMessage(),
            ];
        }

        return ['path' => $destination, 'temporary' => true, 'error' => null];
    }

    /**
     * Path file backup terbaru berdasarkan manifest latest.json pada disk lokal.
     */
    public function latestArchivePath(): ?string
    {
        try {
            $disk = Storage::disk((string) config('backup.local_disk'));
            $directory = trim((string) config('backup.local_path'), '/');
            $manifestPath = $disk->path($directory.'/latest.json');
        } catch (Throwable) {
            return null;
        }

        if (! is_file($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest) || ! is_string($manifest['file'] ?? null)) {
            return null;
        }

        $archive = dirname($manifestPath).'/'.$manifest['file'];

        return is_file($archive) ? $archive : null;
    }

    /**
     * Checksum yang tercatat pada manifest untuk path backup terkait.
     */
    public function manifestChecksum(string $path): ?string
    {
        try {
            $disk = Storage::disk((string) config('backup.local_disk'));
            $directory = trim((string) config('backup.local_path'), '/');
            $manifestPath = $disk->path($directory.'/latest.json');
        } catch (Throwable) {
            return null;
        }

        if (! is_file($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest) || ! is_string($manifest['file'] ?? null)) {
            return null;
        }

        if (realpath(dirname($manifestPath).'/'.$manifest['file']) !== realpath($path)) {
            return null;
        }

        return is_string($manifest['sha256'] ?? null) ? $manifest['sha256'] : null;
    }

    /**
     * @return array{0: array<int, string>, 1: int, 2: array<int, string>}
     */
    private function scan(string $path): array
    {
        $errors = [];
        $tables = [];
        $inserts = 0;

        $handle = @gzopen($path, 'rb');

        if ($handle === false) {
            return [[], 0, ['Arsip backup bukan file gzip yang valid.']];
        }

        try {
            while (! gzeof($handle)) {
                $line = gzgets($handle, 2 * 1024 * 1024);

                if ($line === false) {
                    break;
                }

                if (preg_match('/CREATE TABLE [`"]?([A-Za-z0-9_]+)[`"]?/', $line, $matches) === 1) {
                    $tables[] = $matches[1];
                }

                $inserts += substr_count($line, 'INSERT INTO ');
            }
        } finally {
            gzclose($handle);
        }

        return [array_values(array_unique($tables)), $inserts, $errors];
    }

    /**
     * @param  array<int, string>  $tables
     * @param  array<int, string>  $errors
     * @return array{
     *     path: string,
     *     size: int,
     *     sha256: string,
     *     encrypted: bool,
     *     tables: array<int, string>,
     *     inserts: int,
     *     errors: array<int, string>
     * }
     */
    private function report(
        string $path,
        int $size,
        string $sha256,
        bool $encrypted,
        array $tables,
        int $inserts,
        array $errors,
    ): array {
        return [
            'path' => $path,
            'size' => $size,
            'sha256' => $sha256,
            'encrypted' => $encrypted,
            'tables' => $tables,
            'inserts' => $inserts,
            'errors' => $errors,
        ];
    }
}
