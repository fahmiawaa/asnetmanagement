<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class StagingDataMaskService
{
    /**
     * Samarkan seluruh PII, kredensial, dan identitas perangkat agar salinan
     * database aman dipindahkan ke environment staging. Nilai baru dibuat
     * deterministik dari id sehingga seluruh batas unik tetap terpenuhi.
     *
     * @return array<string, int> jumlah baris yang diubah per tabel
     */
    public function mask(): array
    {
        $counts = [];

        foreach ($this->mappers() as $table => $mapper) {
            if (! $this->hasIdColumn($table)) {
                continue;
            }

            $counts[$table] = $this->maskRows($table, $mapper);
        }

        $counts['sessions'] = $this->clearSessions();

        return $counts;
    }

    /**
     * Estimasi jumlah baris yang akan disamarkan, untuk mode --dry-run.
     *
     * @return array<string, int>
     */
    public function preview(): array
    {
        $counts = [];

        foreach (array_keys($this->mappers()) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] = (int) DB::table($table)->count();
        }

        $counts['sessions'] = Schema::hasTable('sessions')
            ? (int) DB::table('sessions')->count()
            : 0;

        return $counts;
    }

    /**
     * Deskripsi kolom yang disentuh, untuk ditampilkan pada mode dry-run.
     *
     * @return array<string, array<int, string>>
     */
    public function plan(): array
    {
        return [
            'users' => ['name', 'email', 'password'],
            'customers' => ['customer_code', 'name', 'whatsapp_number', 'address', 'latitude', 'longitude', 'notes'],
            'pppoe_accounts' => ['username', 'encrypted_password', 'comment', 'local_address', 'remote_address'],
            'pppoe_active_sessions' => ['username'],
            'mikrotik_devices' => ['name', 'host', 'username', 'encrypted_password', 'notes'],
            'genieacs_servers' => ['name', 'base_url', 'username', 'encrypted_password', 'last_error_message'],
            'ont_devices' => [
                'genieacs_device_id',
                'serial_number',
                'pon_mac',
                'pppoe_mac',
                'ip_address',
                'pppoe_ip',
                'software_version',
            ],
            'invoices' => ['invoice_number', 'notes'],
            'payments' => ['proof_path', 'qris_reference', 'notes'],
            'isolation_logs' => ['reason', 'notes'],
            'audit_logs' => ['description', 'metadata', 'ip_address', 'user_agent'],
            'notifications' => ['data'],
            'sessions' => ['(seluruh baris dihapus)'],
        ];
    }

    /**
     * @return array<string, Closure(object): array<string, mixed>>
     */
    private function mappers(): array
    {
        $domain = (string) config('staging.email_domain');
        $password = Hash::make((string) config('staging.password'));

        return [
            'users' => function (object $row) use ($domain, $password): array {
                $id = (int) $row->id;
                $staf = ($row->role ?? '') !== 'pelanggan';

                return [
                    'name' => $staf ? "Staf Uji {$id}" : 'Pelanggan Uji',
                    'email' => "user{$id}@{$domain}",
                    'password' => $password,
                ];
            },
            'customers' => function (object $row): array {
                $id = (int) $row->id;

                return [
                    'customer_code' => sprintf('STG-%04d', $id),
                    'name' => sprintf('Pelanggan Uji %d', $id),
                    'whatsapp_number' => sprintf('0812%010d', $id),
                    'address' => sprintf('Alamat Uji %d, Staging', $id),
                    'latitude' => null,
                    'longitude' => null,
                    'notes' => 'Data contoh staging.',
                ];
            },
            'pppoe_accounts' => function (object $row): array {
                $id = (int) $row->id;

                return [
                    'username' => sprintf('stg-%04d', $id),
                    'encrypted_password' => Crypt::encryptString('staging-only'),
                    'comment' => 'Akun contoh staging.',
                    'local_address' => '10.10.10.1',
                    'remote_address' => $this->stagingIp($id, 1),
                ];
            },
            'pppoe_active_sessions' => function (object $row): array {
                return ['username' => sprintf('stg-%04d', (int) $row->id)];
            },
            'mikrotik_devices' => function (object $row): array {
                $id = (int) $row->id;

                return [
                    'name' => "MikroTik Staging {$id}",
                    'host' => $this->stagingIp($id, 0),
                    'username' => 'staging',
                    'encrypted_password' => Crypt::encryptString('staging-only'),
                    'notes' => 'Perangkat contoh staging.',
                ];
            },
            'genieacs_servers' => function (object $row): array {
                $id = (int) $row->id;

                return [
                    'name' => "GenieACS Staging {$id}",
                    'base_url' => "http://genieacs-staging-{$id}.internal:7557",
                    'username' => null,
                    'encrypted_password' => null,
                    'last_error_message' => null,
                ];
            },
            'ont_devices' => function (object $row): array {
                $id = (int) $row->id;

                return [
                    'genieacs_device_id' => sprintf('STG-DEV-%04d', $id),
                    'serial_number' => sprintf('STGONT%08d', $id),
                    'pon_mac' => sprintf('02:00:00:00:%02X:%02X', intdiv($id, 256) % 256, $id % 256),
                    'pppoe_mac' => sprintf('02:00:00:01:%02X:%02X', intdiv($id, 256) % 256, $id % 256),
                    'ip_address' => $this->stagingIp($id, 2),
                    'pppoe_ip' => $this->stagingIp($id, 3),
                    'software_version' => 'V0.0.0-staging',
                ];
            },
            'invoices' => function (object $row): array {
                $id = (int) $row->id;

                return [
                    'invoice_number' => sprintf('INV-STG-%06d', $id),
                    'notes' => 'Tagihan contoh staging.',
                ];
            },
            'payments' => function (object $row): array {
                return [
                    'proof_path' => null,
                    'qris_reference' => null,
                    'notes' => 'Pembayaran contoh staging.',
                ];
            },
            'isolation_logs' => function (object $row): array {
                return [
                    'reason' => 'Alasan contoh staging.',
                    'notes' => 'Catatan contoh staging.',
                ];
            },
            'audit_logs' => function (object $row): array {
                return [
                    'description' => 'Log audit pada data staging.',
                    'metadata' => null,
                    'ip_address' => null,
                    'user_agent' => null,
                ];
            },
            'notifications' => function (object $row): array {
                return ['data' => json_encode(['staging' => true], JSON_THROW_ON_ERROR)];
            },
        ];
    }

    /**
     * @param  Closure(object): array<string, mixed>  $mapper
     */
    private function maskRows(string $table, Closure $mapper): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $updated = 0;

        DB::table($table)->select('id')->eachById(function (object $row) use ($mapper, $table, &$updated): void {
            DB::table($table)->where('id', $row->id)->update($mapper($row));
            $updated++;
        }, 500, 'id');

        return $updated;
    }

    private function clearSessions(): int
    {
        if (! Schema::hasTable('sessions')) {
            return 0;
        }

        return DB::table('sessions')->delete();
    }

    private function stagingIp(int $id, int $octet): string
    {
        $third = intdiv($id, 250) + $octet;
        $fourth = ($id % 250) + 1;

        return sprintf('10.10.%d.%d', $third % 256, $fourth);
    }

    private function hasIdColumn(string $table): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return Schema::hasColumn($table, 'id');
    }
}
