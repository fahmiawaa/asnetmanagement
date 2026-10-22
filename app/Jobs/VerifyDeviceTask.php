<?php

namespace App\Jobs;

use App\Models\DeviceTask;
use App\Services\AuditLogService;
use App\Services\GenieAcsTaskService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class VerifyDeviceTask implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public DeviceTask $deviceTask, public int $round = 1) {}

    /**
     * Execute the job.
     */
    public function handle(GenieAcsTaskService $taskService, AuditLogService $audit): void
    {
        $task = $this->deviceTask->fresh();

        if ($task === null || $task->status !== DeviceTask::STATUS_ACCEPTED) {
            return;
        }

        if ($task->expires_at !== null && $task->expires_at->isPast()) {
            $this->expire($task, $audit);

            return;
        }

        $outcome = $this->inspect($taskService, $task);

        if ($outcome['verified']) {
            $task->fill([
                'status' => DeviceTask::STATUS_VERIFIED,
                'verified_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ])->save();

            $audit->record(
                'device_task.verified',
                $task,
                "Task {$task->type} terverifikasi pada perangkat.",
                ['checks' => $outcome['checks']],
            );

            return;
        }

        $maxRounds = max(1, (int) config('genieacs.tasks.verify_attempts'));

        if ($this->round < $maxRounds) {
            VerifyDeviceTask::dispatch($task, $this->round + 1)->delay(
                now()->addSeconds(max(1, (int) config('genieacs.tasks.verify_delay_seconds')))
            );

            return;
        }

        $task->fill([
            'status' => DeviceTask::STATUS_FAILED,
            'failed_at' => now(),
            'error_code' => $outcome['error_code'] ?? 'VERIFICATION_FAILED',
            'error_message' => str($outcome['message'] ?? 'Verifikasi gagal setelah semua percobaan.')
                ->limit(1000)
                ->toString(),
        ])->save();

        $audit->record(
            'device_task.verification_failed',
            $task,
            "Task {$task->type} gagal diverifikasi setelah {$this->round} percobaan.",
            ['checks' => $outcome['checks']],
        );
    }

    /**
     * @return array{verified: bool, checks: array<string, bool>, message: ?string, error_code: ?string}
     */
    private function inspect(GenieAcsTaskService $taskService, DeviceTask $task): array
    {
        $device = $task->ontDevice;
        $server = $device?->genieacsServer;
        $checks = ['inform_advanced' => false, 'ssid_matches' => true];
        $message = null;
        $errorCode = null;

        if ($device === null || $server === null) {
            return [
                'verified' => false,
                'checks' => $checks,
                'message' => 'Perangkat atau server GenieACS tidak ditemukan.',
                'error_code' => 'MISSING_DEVICE',
            ];
        }

        try {
            $lastInform = $taskService->readLastInform($server, $device->genieacs_device_id);
            $checks['inform_advanced'] = $lastInform !== null
                && $lastInform->greaterThan($task->created_at);

            if (! $checks['inform_advanced']) {
                $message = 'Perangkat belum mengirim inform baru setelah task dibuat.';
                $errorCode = 'NO_NEW_INFORM';
            }

            if ($task->type === DeviceTask::TYPE_SET_WIFI) {
                $payload = $this->payload($task);
                $ssidPath = (string) ($payload['ssid_path'] ?? '');
                $expectedSsid = (string) ($payload['ssid'] ?? '');

                if ($ssidPath === '' || $expectedSsid === '') {
                    $checks['ssid_matches'] = false;
                    $message = 'Payload task WiFi tidak lengkap untuk diverifikasi.';
                    $errorCode = 'INVALID_PAYLOAD';
                } else {
                    $currentSsid = $taskService->readParameter(
                        $server,
                        $device->genieacs_device_id,
                        $ssidPath
                    );
                    $checks['ssid_matches'] = $currentSsid !== null
                        && hash_equals($expectedSsid, $currentSsid);

                    if (! $checks['ssid_matches']) {
                        $message = 'SSID perangkat belum sama dengan nilai yang dikirim.';
                        $errorCode = 'SSID_MISMATCH';
                    }
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $message = 'Gagal membaca parameter perangkat untuk verifikasi.';
            $errorCode = 'VERIFY_READ_ERROR';
        }

        return [
            'verified' => $checks['inform_advanced'] && $checks['ssid_matches'],
            'checks' => $checks,
            'message' => $message,
            'error_code' => $errorCode,
        ];
    }

    private function expire(DeviceTask $task, AuditLogService $audit): void
    {
        $task->fill([
            'status' => DeviceTask::STATUS_EXPIRED,
            'failed_at' => now(),
            'error_code' => 'EXPIRED',
            'error_message' => 'Task melewati batas waktu sebelum dapat diverifikasi.',
        ])->save();

        $audit->record('device_task.expired', $task, "Task {$task->type} kedaluwarsa.", []);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DeviceTask $task): array
    {
        $raw = $task->payload_encrypted;

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
