<?php

namespace App\Console\Commands;

use App\Models\GenieacsServer;
use App\Services\GenieAcsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('genieacs:configure {name? : Nama server} {baseUrl? : URL internal NBI, termasuk port 7557} {--username= : Username Basic Auth opsional}')]
#[Description('Simpan konfigurasi server GenieACS dan uji koneksi NBI')]
class ConfigureGenieAcsServer extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GenieAcsService $genieAcs): int
    {
        $name = trim((string) ($this->argument('name') ?: $this->ask('Nama server')));
        $baseUrl = rtrim(trim((string) (
            $this->argument('baseUrl')
            ?: $this->ask('URL internal NBI GenieACS, contoh http://genieacs:7557')
        )), '/');
        $username = trim((string) $this->option('username'));

        if ($name === '' || ! $this->isValidBaseUrl($baseUrl)) {
            $this->error('Nama atau URL NBI tidak valid. Gunakan URL HTTP/HTTPS lengkap.');

            return self::INVALID;
        }

        $server = GenieacsServer::query()->firstOrNew(['base_url' => $baseUrl]);
        $server->name = $name;
        $server->username = $username !== '' ? $username : null;
        $server->is_active = true;

        if ($username !== '') {
            $password = $this->secret(
                $server->exists
                    ? 'Password Basic Auth baru (kosongkan untuk mempertahankan yang lama)'
                    : 'Password Basic Auth'
            );

            if (filled($password)) {
                $server->encrypted_password = $password;
            }
        } else {
            $server->encrypted_password = null;
        }

        $server->save();
        $this->info("Konfigurasi {$server->name} tersimpan. Menguji koneksi...");

        if (! $genieAcs->testConnection($server)) {
            $this->error('Koneksi NBI gagal. Periksa URL, jaringan internal, dan autentikasi.');

            return self::FAILURE;
        }

        $this->info('Koneksi NBI GenieACS berhasil.');

        return self::SUCCESS;
    }

    private function isValidBaseUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && filled(parse_url($url, PHP_URL_HOST));
    }
}
