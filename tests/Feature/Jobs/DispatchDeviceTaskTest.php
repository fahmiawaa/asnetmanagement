<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchDeviceTask;
use App\Jobs\VerifyDeviceTask;
use App\Models\AuditLog;
use App\Models\DeviceTask;
use App\Services\AuditLogService;
use App\Services\GenieAcsTaskService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class DispatchDeviceTaskTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accepted_response_marks_task_accepted_and_queues_verification(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['*commands=1*' => Http::response(['id' => 'remote-1'], 202)]);

        $task = DeviceTask::factory()->create();
        $this->runJob($task);

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_ACCEPTED, $task->status);
        $this->assertNotNull($task->accepted_at);
        $this->assertNull($task->verified_at);
        $this->assertSame('remote-1', $task->remote_task_id);

        Queue::assertPushed(
            VerifyDeviceTask::class,
            fn (VerifyDeviceTask $job): bool => $job->deviceTask->is($task) && $job->round === 1
        );
        Http::assertSent(fn ($request): bool => $request['name'] === 'ConnectionRequest');
    }

    public function test_accepted_task_is_not_marked_verified_before_confirmation(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['*commands=1*' => Http::response('', 202)]);

        $task = DeviceTask::factory()->create();
        $this->runJob($task);

        $task->refresh();
        $this->assertNotSame(DeviceTask::STATUS_VERIFIED, $task->status);
        $this->assertNull($task->verified_at);
        $this->assertNotNull(
            AuditLog::query()->where('action', 'device_task.accepted')->first()
        );
    }

    public function test_rejected_response_retries_until_max_attempts_then_fails(): void
    {
        Queue::fake();
        config(['genieacs.tasks.max_attempts' => 3]);
        Http::preventStrayRequests();
        Http::fake(['*commands=1*' => Http::response('busy', 503)]);

        $task = DeviceTask::factory()->create();

        $this->runJob($task);
        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_PENDING, $task->status);
        $this->assertSame(1, $task->attempts);
        $this->assertSame('GENIEACS_REJECTED', $task->error_code);

        $this->runJob($task);
        $this->assertSame(2, $task->fresh()->attempts);

        $this->runJob($task);
        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_FAILED, $task->status);
        $this->assertSame(3, $task->attempts);
        $this->assertSame('GENIEACS_REJECTED', $task->error_code);
        $this->assertNotNull(
            AuditLog::query()->where('action', 'device_task.failed')->first()
        );
    }

    public function test_expired_task_is_marked_expired_without_calling_genieacs(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake();

        $task = DeviceTask::factory()->create(['expires_at' => now()->subMinute()]);

        $this->runJob($task);

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_EXPIRED, $task->status);
        $this->assertSame('EXPIRED', $task->error_code);
        Http::assertNothingSent();
    }

    public function test_failed_handler_records_dispatch_error(): void
    {
        Queue::fake();

        $task = DeviceTask::factory()->create();
        (new DispatchDeviceTask($task))->failed(new RuntimeException('GenieACS tidak terjangkau'));

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_FAILED, $task->status);
        $this->assertSame('DISPATCH_ERROR', $task->error_code);
        $this->assertStringContainsString('GenieACS tidak terjangkau', (string) $task->error_message);
    }

    public function test_wifi_task_writes_ssid_and_password_parameters(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['*/tasks*' => Http::response(['id' => 'remote-2'], 202)]);

        $task = DeviceTask::factory()->setWifi('ASNet-Baru')->create([
            'payload_encrypted' => json_encode([
                'ssid' => 'ASNet-Baru',
                'password' => 'rahasia-123',
                'ssid_path' => 'InternetGatewayDevice.WiFi.SSID.1.SSID',
                'password_path' => 'VirtualParameters.WlanPassword',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->runJob($task);

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_ACCEPTED, $task->status);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/tasks')
            && $request['objectName'] === 'InternetGatewayDevice.WiFi.SSID.1.SSID'
            && $request['value'] === 'ASNet-Baru');
        Http::assertSent(fn ($request): bool => $request['objectName'] === 'VirtualParameters.WlanPassword'
            && $request['value'] === 'rahasia-123');
    }

    private function runJob(DeviceTask $task): void
    {
        (new DispatchDeviceTask($task))->handle(
            app(GenieAcsTaskService::class),
            app(AuditLogService::class),
        );
    }
}
