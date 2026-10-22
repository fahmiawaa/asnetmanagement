<?php

namespace App\Services;

use App\Models\GenieacsServer;
use App\Models\IsolationLog;
use App\Models\MikrotikDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthService
{
    public function check(bool $includeScheduler = true): array
    {
        $checks = [];
        $this->checkDatabase($checks);
        $this->checkCache($checks);
        $this->checkDisk($checks);
        $this->checkQueue($checks);
        $this->checkMikrotik($checks);
        $this->checkGenieAcs($checks);
        $this->checkAutomation($checks);
        $this->checkBackup($checks);

        if ($includeScheduler) {
            $this->checkScheduler($checks);
        }

        $status = collect($checks)->contains(fn (array $check) => $check['status'] === 'critical')
            ? 'critical'
            : (collect($checks)->contains(fn (array $check) => $check['status'] === 'warning')
                ? 'degraded'
                : 'healthy');

        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    private function add(
        array &$checks,
        string $key,
        string $label,
        string $status,
        string $message,
        mixed $value = null,
    ): void {
        $checks[$key] = compact('label', 'status', 'message', 'value');
    }

    private function checkDatabase(array &$checks): void
    {
        try {
            DB::select('SELECT 1');
            $this->add($checks, 'database', 'Database', 'healthy', 'Koneksi database normal.');
        } catch (Throwable) {
            $this->add($checks, 'database', 'Database', 'critical', 'Database tidak dapat diakses.');
        }
    }

    private function checkCache(array &$checks): void
    {
        try {
            $key = 'operations:health:cache-probe';
            Cache::put($key, 'ok', 60);
            $healthy = Cache::get($key) === 'ok';
            Cache::forget($key);
            $this->add(
                $checks,
                'cache',
                'Cache',
                $healthy ? 'healthy' : 'critical',
                $healthy ? 'Cache normal.' : 'Cache gagal menyimpan nilai.'
            );
        } catch (Throwable) {
            $this->add($checks, 'cache', 'Cache', 'critical', 'Cache tidak dapat diakses.');
        }
    }

    private function checkDisk(array &$checks): void
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if (! $total || $free === false) {
            $this->add($checks, 'disk', 'Penyimpanan', 'warning', 'Kapasitas disk tidak dapat dibaca.');

            return;
        }

        $percent = round(($free / $total) * 100, 1);
        $threshold = (float) config('operations.disk_free_warning_percent');
        $this->add(
            $checks,
            'disk',
            'Penyimpanan',
            $percent < $threshold ? 'critical' : 'healthy',
            "Sisa kapasitas disk {$percent}%.",
            $percent
        );
    }

    private function checkQueue(array &$checks): void
    {
        try {
            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $threshold = (int) config('operations.queue_backlog_warning');
            $status = $failed > 0
                ? 'critical'
                : ($pending >= $threshold ? 'warning' : 'healthy');

            $this->add(
                $checks,
                'queue',
                'Antrean',
                $status,
                "{$pending} job menunggu, {$failed} job gagal.",
                ['pending' => $pending, 'failed' => $failed]
            );
        } catch (Throwable) {
            $this->add($checks, 'queue', 'Antrean', 'warning', 'Status antrean tidak dapat dibaca.');
        }
    }

    private function checkMikrotik(array &$checks): void
    {
        try {
            $active = MikrotikDevice::query()->where('connection_status', '!=', 'inactive');
            $total = (clone $active)->count();
            $cutoff = now()->subMinutes((int) config('operations.mikrotik_sync_max_age_minutes'));
            $problem = (clone $active)
                ->where(function ($query) use ($cutoff): void {
                    $query->where('connection_status', 'failed')
                        ->orWhereNull('last_sync_at')
                        ->orWhere('last_sync_at', '<', $cutoff);
                })
                ->count();

            $this->add(
                $checks,
                'mikrotik',
                'MikroTik',
                $total === 0 || $problem > 0 ? 'critical' : 'healthy',
                "{$total} perangkat aktif, {$problem} bermasalah atau belum sinkron.",
                ['active' => $total, 'problem' => $problem]
            );
        } catch (Throwable) {
            $this->add($checks, 'mikrotik', 'MikroTik', 'warning', 'Status MikroTik tidak dapat dibaca.');
        }
    }

    private function checkAutomation(array &$checks): void
    {
        try {
            $failed = IsolationLog::query()->where('approval_status', 'failed')->count();
            $stale = IsolationLog::query()
                ->where('approval_status', 'processing')
                ->where('last_attempt_at', '<', now()->subMinutes(
                    (int) config('operations.processing_timeout_minutes')
                ))
                ->count();

            $this->add(
                $checks,
                'automation',
                'Auto isolir / restore',
                $failed > 0 || $stale > 0 ? 'critical' : 'healthy',
                "{$failed} aksi gagal, {$stale} aksi macet.",
                ['failed' => $failed, 'stale' => $stale]
            );
        } catch (Throwable) {
            $this->add($checks, 'automation', 'Auto isolir / restore', 'warning', 'Status otomasi tidak dapat dibaca.');
        }
    }

    private function checkGenieAcs(array &$checks): void
    {
        try {
            if (! Schema::hasTable('genieacs_servers')) {
                $this->add(
                    $checks,
                    'genieacs',
                    'GenieACS',
                    'warning',
                    'Tabel konfigurasi GenieACS belum tersedia.'
                );

                return;
            }

            $active = GenieacsServer::query()->where('is_active', true);
            $total = (clone $active)->count();

            if ($total === 0) {
                $this->add(
                    $checks,
                    'genieacs',
                    'GenieACS',
                    'warning',
                    'Server GenieACS aktif belum dikonfigurasi.',
                    ['active' => 0, 'problem' => 0]
                );

                return;
            }

            $cutoff = now()->subMinutes((int) config('genieacs.sync_max_age_minutes'));
            $problem = (clone $active)
                ->where(function ($query) use ($cutoff): void {
                    $query->where('connection_status', 'failed')
                        ->orWhereNull('last_sync_at')
                        ->orWhere('last_sync_at', '<', $cutoff);
                })
                ->count();

            $this->add(
                $checks,
                'genieacs',
                'GenieACS',
                $problem > 0 ? 'critical' : 'healthy',
                "{$total} server aktif, {$problem} gagal atau belum sinkron.",
                ['active' => $total, 'problem' => $problem]
            );
        } catch (Throwable) {
            $this->add(
                $checks,
                'genieacs',
                'GenieACS',
                'warning',
                'Status GenieACS tidak dapat dibaca.'
            );
        }
    }

    private function checkBackup(array &$checks): void
    {
        $local = $this->readManifest(
            (string) config('backup.local_disk'),
            (string) config('backup.local_path')
        );

        if (! $local || empty($local['created_at'])) {
            $this->add($checks, 'backup', 'Backup', 'critical', 'Backup sukses belum ditemukan.');

            return;
        }

        try {
            $createdAt = Carbon::parse($local['created_at']);
        } catch (Throwable) {
            $this->add($checks, 'backup', 'Backup', 'critical', 'Manifest backup tidak valid.');

            return;
        }

        $ageHours = round($createdAt->diffInMinutes(now()) / 60, 1);
        $tooOld = $ageHours > (int) config('operations.backup_max_age_hours');
        $offsiteRequired = (bool) config('backup.offsite_enabled');
        $offsiteOk = ! $offsiteRequired || ($local['offsite_uploaded'] ?? false);

        $this->add(
            $checks,
            'backup',
            'Backup',
            $tooOld || ! $offsiteOk ? 'critical' : 'healthy',
            "Backup terakhir {$ageHours} jam lalu; offsite ".($offsiteOk ? 'tersedia.' : 'belum tersedia.'),
            [
                'created_at' => $createdAt->toIso8601String(),
                'age_hours' => $ageHours,
                'offsite' => $offsiteOk,
            ]
        );
    }

    private function checkScheduler(array &$checks): void
    {
        try {
            $heartbeat = Cache::get('operations:scheduler:last_run_at');

            if (! $heartbeat) {
                $this->add($checks, 'scheduler', 'Scheduler', 'critical', 'Heartbeat scheduler belum ditemukan.');

                return;
            }

            $age = Carbon::parse($heartbeat)->diffInMinutes(now());
            $maxAge = (int) config('operations.scheduler_max_age_minutes');
            $this->add(
                $checks,
                'scheduler',
                'Scheduler',
                $age > $maxAge ? 'critical' : 'healthy',
                "Heartbeat scheduler {$age} menit lalu.",
                $age
            );
        } catch (Throwable) {
            $this->add($checks, 'scheduler', 'Scheduler', 'critical', 'Heartbeat scheduler tidak dapat dibaca.');
        }
    }

    private function readManifest(string $disk, string $path): ?array
    {
        try {
            $manifest = trim($path, '/').'/latest.json';

            if (! Storage::disk($disk)->exists($manifest)) {
                return null;
            }

            return json_decode(
                Storage::disk($disk)->get($manifest),
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return null;
        }
    }
}
