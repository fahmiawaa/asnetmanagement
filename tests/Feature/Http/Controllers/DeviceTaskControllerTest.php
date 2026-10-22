<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\DispatchDeviceTask;
use App\Models\DeviceTask;
use App\Models\OntDevice;
use App\Models\OntParameterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeviceTaskControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('device-tasks.index'))->assertRedirect(route('login'));

        $device = OntDevice::factory()->create();
        $this->post(route('ont-devices.tasks.summon', $device))->assertRedirect(route('login'));
    }

    public function test_customer_role_cannot_see_index_or_run_task(): void
    {
        $user = User::factory()->create(['role' => 'pelanggan']);
        $device = OntDevice::factory()->create();

        $this->actingAs($user)->get(route('device-tasks.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('ont-devices.tasks.summon', $device))
            ->assertForbidden();

        $this->assertDatabaseCount('device_tasks', 0);
    }

    public function test_admin_can_view_index_but_cannot_run_task(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $device = OntDevice::factory()->create();

        $this->actingAs($user)->get(route('device-tasks.index'))->assertOk();
        $this->actingAs($user)
            ->post(route('ont-devices.tasks.reboot', $device))
            ->assertForbidden();

        $this->assertDatabaseCount('device_tasks', 0);
    }

    public function test_technician_can_send_summon_task(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'teknisi']);
        $device = OntDevice::factory()->create();

        $this->actingAs($user)
            ->post(route('ont-devices.tasks.summon', $device))
            ->assertRedirect(route('ont-devices.show', $device))
            ->assertSessionHas('success');

        $task = DeviceTask::query()->firstOrFail();
        $this->assertSame(DeviceTask::TYPE_SUMMON, $task->type);
        $this->assertSame(DeviceTask::STATUS_PENDING, $task->status);
        $this->assertSame($user->getKey(), $task->requested_by);
        Queue::assertPushed(DispatchDeviceTask::class);
    }

    public function test_wifi_task_requires_profile_and_valid_password(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'teknisi']);
        $device = OntDevice::factory()->create();

        $this->actingAs($user)
            ->from(route('ont-devices.show', $device))
            ->post(route('ont-devices.tasks.wifi', $device), [
                'ssid' => 'ASNet',
                'password' => 'rahasia-123',
            ])
            ->assertSessionHasErrors('profile_id');

        $this->actingAs($user)
            ->from(route('ont-devices.show', $device))
            ->post(route('ont-devices.tasks.wifi', $device), [
                'ssid' => 'ASNet',
                'password' => 'pendek',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame('ASNet', session()->getOldInput('ssid'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertDatabaseCount('device_tasks', 0);
    }

    public function test_wifi_task_is_created_without_password_in_flash(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'teknisi']);
        $device = OntDevice::factory()->create();
        OntParameterProfile::factory()->forDevice($device->manufacturer)->create();

        $this->actingAs($user)
            ->post(route('ont-devices.tasks.wifi', $device), [
                'ssid' => 'ASNet-Baru',
                'password' => 'rahasia-123',
            ])
            ->assertRedirect(route('ont-devices.show', $device));

        $this->assertDatabaseHas('device_tasks', [
            'ont_device_id' => $device->getKey(),
            'type' => DeviceTask::TYPE_SET_WIFI,
            'status' => DeviceTask::STATUS_PENDING,
        ]);

        $success = (string) session('success');
        $this->assertStringContainsString('set_wifi', $success);
        $this->assertStringNotContainsString('rahasia-123', $success);
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_technician_can_cancel_open_task(): void
    {
        $user = User::factory()->create(['role' => 'teknisi']);
        $task = DeviceTask::factory()->create(['requested_by' => $user->getKey()]);

        $this->actingAs($user)
            ->post(route('device-tasks.cancel', $task))
            ->assertRedirect(route('device-tasks.index'))
            ->assertSessionHas('success');

        $this->assertSame(DeviceTask::STATUS_CANCELLED, $task->fresh()->status);
    }

    public function test_admin_cannot_cancel_task(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $task = DeviceTask::factory()->create();

        $this->actingAs($user)->post(route('device-tasks.cancel', $task))->assertForbidden();
        $this->assertSame(DeviceTask::STATUS_PENDING, $task->fresh()->status);
    }

    public function test_index_filters_by_status_and_type(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $verifiedDevice = OntDevice::factory()->create(['serial_number' => 'TASKVERIF01']);
        $failedDevice = OntDevice::factory()->create(['serial_number' => 'TASKFAILED01']);

        DeviceTask::factory()->verified()->create(['ont_device_id' => $verifiedDevice->getKey()]);
        DeviceTask::factory()->reboot()->failed()->create(['ont_device_id' => $failedDevice->getKey()]);

        $this->actingAs($user)
            ->get(route('device-tasks.index', ['status' => DeviceTask::STATUS_FAILED]))
            ->assertOk()
            ->assertSee('TASKFAILED01')
            ->assertDontSee('TASKVERIF01');

        $this->actingAs($user)
            ->get(route('device-tasks.index', ['type' => DeviceTask::TYPE_REBOOT]))
            ->assertOk()
            ->assertSee('TASKFAILED01')
            ->assertDontSee('TASKVERIF01');
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user)
            ->get(route('device-tasks.index', ['status' => 'bukan-status']))
            ->assertSessionHasErrors('status');
    }

    public function test_device_detail_page_shows_task_section(): void
    {
        $user = User::factory()->create(['role' => 'teknisi']);
        $device = OntDevice::factory()->create();
        $task = DeviceTask::factory()->create(['ont_device_id' => $device->getKey()]);

        $this->actingAs($user)
            ->get(route('ont-devices.show', $device))
            ->assertOk()
            ->assertSee('Task Perangkat')
            ->assertSee(route('device-tasks.index'));
    }
}
