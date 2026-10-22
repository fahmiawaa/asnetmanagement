<?php

namespace Tests\Feature\Jobs;

use App\Jobs\VerifyDeviceTask;
use App\Models\AuditLog;
use App\Models\DeviceTask;
use App\Services\AuditLogService;
use App\Services\GenieAcsTaskService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VerifyDeviceTaskTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_task_is_verified_when_inform_advances_after_creation(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            '*/devices*' => Http::response([
                '_lastInform' => now()->addMinute()->toIso8601String(),
            ], 200),
        ]);

        $task = DeviceTask::factory()->accepted()->create();

        $this->runJob($task);

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_VERIFIED, $task->status);
        $this->assertNotNull($task->verified_at);
        $this->assertNotNull(
            AuditLog::query()->where('action', 'device_task.verified')->first()
        );
        Queue::assertNothingPushed();
    }

    public function test_stale_inform_keeps_task_accepted_and_schedules_next_round(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            '*/devices*' => Http::response([
                '_lastInform' => now()->subHours(3)->toIso8601String(),
            ], 200),
        ]);

        $task = DeviceTask::factory()->accepted()->create();

        $this->runJob($task);

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_ACCEPTED, $task->status);
        $this->assertNull($task->verified_at);
        Queue::assertPushed(
            VerifyDeviceTask::class,
            fn (VerifyDeviceTask $job): bool => $job->round === 2
        );
    }

    public function test_task_fails_after_all_verification_rounds_are_exhausted(): void
    {
        Queue::fake();
        config(['genieacs.tasks.verify_attempts' => 5]);
        Http::preventStrayRequests();
        Http::fake([
            '*/devices*' => Http::response([
                '_lastInform' => now()->subHours(3)->toIso8601String(),
            ], 200),
        ]);

        $task = DeviceTask::factory()->accepted()->create();

        $this->runJob($task, 5);

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_FAILED, $task->status);
        $this->assertSame('NO_NEW_INFORM', $task->error_code);
        $this->assertNotNull(
            AuditLog::query()->where('action', 'device_task.verification_failed')->first()
        );
        Queue::assertNothingPushed();
    }

    public function test_wifi_task_is_verified_when_ssid_matches(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(fn (Request $request): PromiseInterface => $this->deviceResponse($request, 'ASNet-Baru'));

        $task = DeviceTask::factory()->setWifi('ASNet-Baru')->accepted()->create([
            'payload_encrypted' => json_encode([
                'ssid' => 'ASNet-Baru',
                'password' => 'rahasia-123',
                'ssid_path' => 'InternetGatewayDevice.WiFi.SSID.1.SSID',
                'password_path' => 'VirtualParameters.WlanPassword',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->runJob($task);

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_VERIFIED, $task->status);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'InternetGatewayDevice.WiFi.SSID.1.SSID'));
    }

    public function test_wifi_task_fails_when_ssid_does_not_match(): void
    {
        Queue::fake();
        config(['genieacs.tasks.verify_attempts' => 1]);
        Http::preventStrayRequests();
        Http::fake(fn (Request $request): PromiseInterface => $this->deviceResponse($request, 'SSID-Lama'));

        $task = DeviceTask::factory()->setWifi('ASNet-Baru')->accepted()->create([
            'payload_encrypted' => json_encode([
                'ssid' => 'ASNet-Baru',
                'password' => 'rahasia-123',
                'ssid_path' => 'InternetGatewayDevice.WiFi.SSID.1.SSID',
                'password_path' => 'VirtualParameters.WlanPassword',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->runJob($task);

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_FAILED, $task->status);
        $this->assertSame('SSID_MISMATCH', $task->error_code);
        $this->assertStringNotContainsString('rahasia-123', (string) $task->error_message);
    }

    public function test_accepted_task_with_past_expiry_is_marked_expired(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake();

        $task = DeviceTask::factory()->accepted()->create(['expires_at' => now()->subMinute()]);

        $this->runJob($task);

        $task->refresh();
        $this->assertSame(DeviceTask::STATUS_EXPIRED, $task->status);
        $this->assertSame('EXPIRED', $task->error_code);
        Http::assertNothingSent();
    }

    public function test_pending_task_is_ignored(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake();

        $task = DeviceTask::factory()->create(['status' => DeviceTask::STATUS_PENDING]);

        $this->runJob($task);

        $this->assertSame(DeviceTask::STATUS_PENDING, $task->fresh()->status);
        Http::assertNothingSent();
    }

    private function runJob(DeviceTask $task, int $round = 1): void
    {
        (new VerifyDeviceTask($task, $round))->handle(
            app(GenieAcsTaskService::class),
            app(AuditLogService::class),
        );
    }

    private function projection(Request $request): string
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return (string) ($query['projection'] ?? '');
    }

    private function deviceResponse(Request $request, string $ssid): PromiseInterface
    {
        $projection = $this->projection($request);

        if ($projection === '_lastInform') {
            return Http::response([
                '_lastInform' => now()->addMinute()->toIso8601String(),
            ], 200);
        }

        return Http::response([
            'InternetGatewayDevice' => [
                'WiFi' => ['SSID' => ['1' => ['SSID' => $ssid]]],
            ],
        ], 200);
    }
}
