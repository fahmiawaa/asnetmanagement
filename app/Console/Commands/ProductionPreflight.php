<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductionPreflight extends Command
{
    protected $signature = 'asnet:preflight {--production : Jalankan pemeriksaan ketat produksi}';

    protected $description = 'Memeriksa kesiapan konfigurasi ASNet sebelum deployment';

    public function handle(): int
    {
        $failed = 0;
        $production = (bool) $this->option('production');

        $checks = [
            'APP_KEY tersedia' => (bool) config('app.key'),
            'APP_DEBUG nonaktif' => ! config('app.debug') || ! $production,
            'Storage dapat ditulis' => is_writable(storage_path()),
            'Bootstrap cache dapat ditulis' => is_writable(base_path('bootstrap/cache')),
            'Owner aktif tersedia' => User::query()->where('role', 'owner')->where('active', true)->exists(),
        ];

        try {
            DB::connection()->getPdo();
            $checks['Database dapat diakses'] = true;
        } catch (\Throwable $exception) {
            $checks['Database dapat diakses'] = false;
            $this->line('  Database: '.$exception->getMessage());
        }

        try {
            $lock = Cache::lock('asnet:preflight-lock', 10);
            $checks['Cache lock tersedia'] = $lock->get();

            if ($checks['Cache lock tersedia']) {
                $lock->release();
            }
        } catch (\Throwable $exception) {
            $checks['Cache lock tersedia'] = false;
            $this->line('  Cache lock: '.$exception->getMessage());
        }

        if (config('mikrotik.auto_isolation_enabled')) {
            $batchSize = (int) config('mikrotik.isolation_batch_size', 25);
            $failureThreshold = (int) config(
                'mikrotik.isolation_failure_threshold',
                3
            );
            $checks['Real action MikroTik aktif'] = (bool) config(
                'mikrotik.allow_real_actions'
            );
            $checks['Batch isolir maksimal 50'] = $batchSize <= 50;
            $checks['Circuit breaker lebih kecil dari batch'] =
                $failureThreshold <= $batchSize;
            $checks['Masa toleransi minimal 1 hari'] =
                (int) config('mikrotik.grace_period_days', 7) >= 1;
            $checks['Perangkat MikroTik aktif tersedia'] =
                MikrotikDevice::query()
                    ->where('connection_status', '!=', 'inactive')
                    ->exists();
        }

        if (config('payments.qris.enabled')) {
            $qrisPath = config('payments.qris.image_path');
            $checks['File QRIS tersedia'] = is_string($qrisPath)
                && File::isFile(public_path($qrisPath));
        }

        if ($production) {
            $alertEmails = config('operations.alert.emails', []);
            $webhookUrl = config('operations.alert.webhook_url');

            $checks['Registrasi publik nonaktif'] = ! config(
                'asnet.public_registration'
            );
            $checks['Session cookie hanya melalui HTTPS'] =
                config('session.secure') === true;
            $checks['Session cookie HTTP-only'] =
                config('session.http_only') === true;
            $checks['Session cookie SameSite aman'] = in_array(
                config('session.same_site'),
                ['lax', 'strict'],
                true
            );
            $checks['Backup offsite diaktifkan'] = (bool) config('backup.offsite_enabled');
            $checks['Token health endpoint tersedia'] = filled(config('operations.health_token'));
            $checks['Kanal alert tersedia'] = count($alertEmails) > 0 || filled($webhookUrl);
            $checks['Email alert tidak memakai log driver'] =
                count($alertEmails) === 0 || config('mail.default') !== 'log';
        }

        foreach ($checks as $label => $passed) {
            if ($passed) {
                $this->info("[OK] {$label}");
            } else {
                $this->error("[FAIL] {$label}");
                $failed++;
            }
        }

        if (! config('mikrotik.allow_real_actions')) {
            $this->warn('[INFO] Real action MikroTik masih nonaktif.');
        }

        if ($production && config('app.debug')) {
            $this->error('APP_DEBUG=true tidak diperbolehkan pada mode produksi.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
