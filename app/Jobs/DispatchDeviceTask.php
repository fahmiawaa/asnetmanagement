<?php

namespace App\Jobs;

use App\Models\DeviceTask;
use App\Models\GenieacsServer;
use App\Services\AuditLogService;
use App\Services\GenieAcsTaskService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DispatchDeviceTask implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public DeviceTask $deviceTask) {}

    /**
     * Execute the job.
     */
    public function handle(GenieAcsTaskService $taskService, AuditLogService $audit): void
    {
        $task = $this->deviceTask->fresh();

        if ($task === null || $task->isTerminal()) {
            return;
        }

        if ($task->expires_at !== null && $task->expires_at->isPast()) {
            $this->expire($task, $audit);

            return;
        }

        $server = $task->ontDevice?->genieacsServer;

        if ($server === null) {
            $this->fail($task, 'NO_SERVER', 'Server GenieACS untuk perangkat ini tidak ditemukan.', $audit);

            return;
        }

        $task->fill([
            'status' => DeviceTask::STATUS_PROCESSING,
            'attempts' => $task->attempts + 1,
            'error_code' => null,
            'error_message' => null,
        ])->save();

        $result = $this->send($taskService, $server, $task);

        if ($result['accepted']) {
            $this->accept($task, $result, $audit);

            return;
        }

        $errorCode = $result['error_code'] ?? 'GENIEACS_REJECTED';
        $errorMessage = $result['error'] ?? 'GenieACS tidak menerima perintah.';

        if ($task->attempts >= max(1, (int) config('genieacs.tasks.max_attempts'))) {
            $this->fail($task, $errorCode, $errorMessage, $audit);

            return;
        }

        $task->fill([
            'status' => DeviceTask::STATUS_PENDING,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ])->save();

        $audit->record(
            'device_task.retry',
            $task,
            "Percobaan ke-{$task->attempts} task {$task->type} gagal, dijadwalkan ulang.",
            ['attempts' => $task->attempts, 'error_code' => $errorCode],
        );

        self::dispatch($task)->delay(
            now()->addSeconds(max(1, (int) config('genieacs.tasks.retry_delay_seconds')))
        );
    }

    public function failed(?Throwable $exception): void
    {
        $task = $this->deviceTask->fresh();

        if ($task === null || $task->isTerminal()) {
            return;
        }

        $this->fail(
            $task,
            'DISPATCH_ERROR',
            $exception?->getMessage() ?? 'Proses pengiriman task gagal.',
            app(AuditLogService::class),
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function accept(DeviceTask $task, array $result, AuditLogService $audit): void
    {
        $task->fill([
            'status' => DeviceTask::STATUS_ACCEPTED,
            'accepted_at' => $task->accepted_at ?? now(),
            'remote_task_id' => $result['remote_task_id'] ?? $task->remote_task_id,
            'error_code' => null,
            'error_message' => null,
        ])->save();

        $audit->record(
            'device_task.accepted',
            $task,
            "Task {$task->type} diterima GenieACS dan menunggu verifikasi.",
            [
                'status_code' => $result['status_code'] ?? null,
                'remote_task_id' => $task->remote_task_id,
                'attempts' => $task->attempts,
            ],
        );

        VerifyDeviceTask::dispatch($task)->delay(
            now()->addSeconds(max(1, (int) config('genieacs.tasks.verify_delay_seconds')))
        );
    }

    private function fail(
        DeviceTask $task,
        string $errorCode,
        string $errorMessage,
        AuditLogService $audit,
    ): void {
        $task->fill([
            'status' => DeviceTask::STATUS_FAILED,
            'failed_at' => now(),
            'error_code' => $errorCode,
            'error_message' => str($errorMessage)->limit(1000)->toString(),
        ])->save();

        $audit->record(
            'device_task.failed',
            $task,
            "Task {$task->type} gagal setelah {$task->attempts} percobaan.",
            ['error_code' => $errorCode],
        );
    }

    private function expire(DeviceTask $task, AuditLogService $audit): void
    {
        $task->fill([
            'status' => DeviceTask::STATUS_EXPIRED,
            'failed_at' => now(),
            'error_code' => 'EXPIRED',
            'error_message' => 'Task melewati batas waktu sebelum diterima GenieACS.',
        ])->save();

        $audit->record('device_task.expired', $task, "Task {$task->type} kedaluwarsa.", []);
    }

    /**
     * @return array<string, mixed>
     */
    private function send(
        GenieAcsTaskService $taskService,
        GenieacsServer $server,
        DeviceTask $task,
    ): array {
        $deviceId = (string) $task->ontDevice?->genieacs_device_id;

        try {
            return match ($task->type) {
                DeviceTask::TYPE_SUMMON => $taskService->summon($server, $deviceId),
                DeviceTask::TYPE_REBOOT => $taskService->reboot($server, $deviceId),
                DeviceTask::TYPE_SET_WIFI => $this->sendWifi($taskService, $server, $task, $deviceId),
                default => [
                    'accepted' => false,
                    'status_code' => 0,
                    'remote_task_id' => null,
                    'error' => 'Tipe task tidak dikenal.',
                    'error_code' => 'UNKNOWN_TYPE',
                ],
            };
        } catch (Throwable $exception) {
            report($exception);

            return [
                'accepted' => false,
                'status_code' => 0,
                'remote_task_id' => null,
                'error' => $exception->getMessage(),
                'error_code' => 'REQUEST_ERROR',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sendWifi(
        GenieAcsTaskService $taskService,
        GenieacsServer $server,
        DeviceTask $task,
        string $deviceId,
    ): array {
        $payload = $this->payload($task);
        $ssid = (string) ($payload['ssid'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        if ($ssid === '' || $password === '') {
            return [
                'accepted' => false,
                'status_code' => 0,
                'remote_task_id' => null,
                'error' => 'Payload WiFi pada task tidak lengkap.',
                'error_code' => 'INVALID_PAYLOAD',
            ];
        }

        $ssidPath = (string) ($payload['ssid_path'] ?? '');
        $passwordPath = (string) ($payload['password_path'] ?? '');

        $ssidResult = $ssidPath === ''
            ? $this->rejected('SSID path belum ditentukan profil.', 'INVALID_PAYLOAD')
            : $taskService->setParameter($server, $deviceId, $ssidPath, $ssid);

        if (! $ssidResult['accepted']) {
            return $ssidResult;
        }

        if ($passwordPath === '') {
            return $this->rejected('Password path belum ditentukan profil.', 'INVALID_PAYLOAD');
        }

        $passwordResult = $taskService->setParameter($server, $deviceId, $passwordPath, $password);

        if ($passwordResult['accepted']) {
            $passwordResult['remote_task_id'] = $ssidResult['remote_task_id'];
        }

        return $passwordResult;
    }

    /**
     * @return array<string, mixed>
     */
    private function rejected(string $message, string $code): array
    {
        return [
            'accepted' => false,
            'status_code' => 0,
            'remote_task_id' => null,
            'error' => $message,
            'error_code' => $code,
        ];
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
