<?php

namespace Tests\Feature\Services;

use App\Jobs\DispatchDeviceTask;
use App\Models\AuditLog;
use App\Models\DeviceTask;
use App\Models\OntDevice;
use App\Models\OntParameterProfile;
use App\Models\User;
use App\Services\DeviceTaskService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeviceTaskServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private OntDevice $device;

    private User $user;

    private DeviceTaskService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = OntDevice::factory()->create();
        $this->user = User::factory()->create();
        $this->service = app(DeviceTaskService::class);
    }

    public function test_request_creates_pending_task_and_dispatches_job(): void
    {
        Queue::fake();

        $task = $this->service->request($this->device, DeviceTask::TYPE_SUMMON, $this->user);

        $this->assertSame(DeviceTask::STATUS_PENDING, $task->status);
        $this->assertTrue($task->isOpen());
        $this->assertTrue($task->expires_at->isFuture());
        $this->assertSame($this->user->getKey(), $task->requested_by);
        Queue::assertPushed(DispatchDeviceTask::class, fn (DispatchDeviceTask $job): bool => $job->deviceTask->is($task));

        $audit = AuditLog::query()->where('action', 'device_task.requested')->first();
        $this->assertNotNull($audit);
        $this->assertSame($task->getKey(), $audit->auditable_id);
    }

    public function test_request_rejects_duplicate_open_task_of_same_type(): void
    {
        Queue::fake();
        DeviceTask::factory()->reboot()->create(['ont_device_id' => $this->device->getKey()]);

        try {
            $this->service->request($this->device, DeviceTask::TYPE_REBOOT, $this->user);
            $this->fail('Task duplikat seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
            $this->assertStringContainsString('berstatus', $exception->errors()['type'][0]);
        }
    }

    public function test_request_rejects_unknown_type(): void
    {
        Queue::fake();

        $this->expectException(ValidationException::class);
        $this->service->request($this->device, 'format', $this->user);
    }

    public function test_request_is_disabled_by_configuration(): void
    {
        Queue::fake();
        config(['genieacs.tasks.enabled' => false]);

        try {
            $this->service->request($this->device, DeviceTask::TYPE_SUMMON, $this->user);
            $this->fail('Task seharusnya ditolak ketika dinonaktifkan.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('dinonaktifkan', $exception->errors()['type'][0]);
        }

        $this->assertDatabaseCount('device_tasks', 0);
    }

    public function test_set_wifi_stores_password_encrypted_and_outside_safe_summary(): void
    {
        Queue::fake();
        $profile = OntParameterProfile::factory()->forDevice($this->device->manufacturer)->create();

        $task = $this->service->request($this->device, DeviceTask::TYPE_SET_WIFI, $this->user, [
            'ssid' => 'ASNet-Baru',
            'password' => 'rahasia-123',
            'profile_id' => $profile->getKey(),
        ]);

        $raw = (string) DB::table('device_tasks')->where('id', $task->getKey())->value('payload_encrypted');
        $this->assertStringNotContainsString('rahasia-123', $raw);
        $this->assertStringNotContainsString('ASNet-Baru', $raw);

        $this->assertSame('ASNet-Baru', $task->safe_summary['ssid']);
        $this->assertArrayNotHasKey('password', $task->safe_summary);

        $payload = json_decode((string) $task->payload_encrypted, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('rahasia-123', $payload['password']);
        $this->assertSame($profile->ssidPaths()[0], $payload['ssid_path']);
        $this->assertSame($profile->passwordPaths()[0], $payload['password_path']);

        $audit = AuditLog::query()->where('action', 'device_task.requested')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('rahasia-123', json_encode($audit->metadata));
        $this->assertStringNotContainsString('password', json_encode($audit->metadata));
    }

    public function test_set_wifi_rejects_unverified_profile(): void
    {
        Queue::fake();
        $profile = OntParameterProfile::factory()->unverified()->forDevice($this->device->manufacturer)->create();

        try {
            $this->service->request($this->device, DeviceTask::TYPE_SET_WIFI, $this->user, [
                'ssid' => 'ASNet',
                'password' => 'rahasia-123',
                'profile_id' => $profile->getKey(),
            ]);
            $this->fail('Profil belum diverifikasi seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('profile_id', $exception->errors());
        }
    }

    public function test_set_wifi_rejects_profile_for_other_device(): void
    {
        Queue::fake();
        $profile = OntParameterProfile::factory()->forDevice('Vendor-Tidak-Sama')->create();

        try {
            $this->service->request($this->device, DeviceTask::TYPE_SET_WIFI, $this->user, [
                'ssid' => 'ASNet',
                'password' => 'rahasia-123',
                'profile_id' => $profile->getKey(),
            ]);
            $this->fail('Profil yang tidak cocok seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('profile_id', $exception->errors());
        }
    }

    public function test_set_wifi_picks_most_specific_matching_profile(): void
    {
        Queue::fake();
        $generic = OntParameterProfile::factory()->create([
            'name' => 'Profil Generik',
            'manufacturer' => null,
            'product_class' => null,
            'pon_mode' => null,
        ]);
        $specific = OntParameterProfile::factory()->forDevice(
            $this->device->manufacturer,
            $this->device->product_class,
            $this->device->pon_mode,
        )->create();

        $task = $this->service->request($this->device, DeviceTask::TYPE_SET_WIFI, $this->user, [
            'ssid' => 'ASNet',
            'password' => 'rahasia-123',
        ]);

        $this->assertSame($specific->getKey(), $task->safe_summary['profile_id']);
        $this->assertNotSame($generic->getKey(), $task->safe_summary['profile_id']);
    }

    public function test_set_wifi_validates_password_length(): void
    {
        Queue::fake();
        OntParameterProfile::factory()->forDevice($this->device->manufacturer)->create();

        try {
            $this->service->request($this->device, DeviceTask::TYPE_SET_WIFI, $this->user, [
                'ssid' => 'ASNet',
                'password' => 'pendek',
            ]);
            $this->fail('Password pendek seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }
    }

    public function test_cancel_marks_task_cancelled_and_writes_audit(): void
    {
        $task = DeviceTask::factory()->create([
            'ont_device_id' => $this->device->getKey(),
            'requested_by' => $this->user->getKey(),
        ]);

        $cancelled = $this->service->cancel($task, $this->user);

        $this->assertSame(DeviceTask::STATUS_CANCELLED, $cancelled->status);
        $this->assertFalse($cancelled->isOpen());
        $this->assertSame('CANCELLED', $cancelled->error_code);
        $this->assertNotNull(
            AuditLog::query()->where('action', 'device_task.cancelled')->first()
        );
    }

    public function test_cancel_rejects_terminal_task(): void
    {
        $task = DeviceTask::factory()->verified()->create(['ont_device_id' => $this->device->getKey()]);

        $this->expectException(ValidationException::class);
        $this->service->cancel($task, $this->user);
    }
}
