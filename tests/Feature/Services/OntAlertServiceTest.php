<?php

namespace Tests\Feature\Services;

use App\Models\OntDevice;
use App\Models\User;
use App\Notifications\AsnetNotification;
use App\Services\OntAlertService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OntAlertServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private OntAlertService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OntAlertService::class);
        Notification::fake();
    }

    public function test_offline_device_sends_notification_to_owner_and_admin(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'teknisi']);
        $ont = OntDevice::factory()->create([
            'serial_number' => 'OFFLINEALERT01',
            'connection_status' => 'offline',
            'rx_power_quality' => 'good',
        ]);

        $result = $this->service->scan();

        $this->assertSame(1, $result['offline']);
        $this->assertSame(0, $result['optical']);
        $this->assertSame(1, $result['notified']);

        Notification::assertSentTo(
            [$owner, $admin],
            AsnetNotification::class,
            fn (AsnetNotification $notification): bool => str_contains($notification->title, 'OFFLINEALERT01')
                && $notification->url === route('optical-alerts.index')
        );
        Notification::assertNotSentTo($technician, AsnetNotification::class);
    }

    public function test_critical_rx_power_sends_optical_notification(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        OntDevice::factory()->create([
            'serial_number' => 'CRITICALRX001',
            'connection_status' => 'online',
            'rx_power_quality' => 'critical',
            'rx_power_dbm' => -29.50,
        ]);

        $result = $this->service->scan();

        $this->assertSame(0, $result['offline']);
        $this->assertSame(1, $result['optical']);
        Notification::assertSentTo(
            $owner,
            AsnetNotification::class,
            fn (AsnetNotification $notification): bool => str_contains($notification->title, 'CRITICALRX001')
                && str_contains($notification->message, '-29.5')
        );
    }

    public function test_healthy_device_is_not_notified(): void
    {
        User::factory()->create(['role' => 'owner']);
        OntDevice::factory()->create([
            'serial_number' => 'HEALTHYONT001',
            'connection_status' => 'online',
            'rx_power_quality' => 'good',
        ]);

        $result = $this->service->scan();

        $this->assertSame(['offline' => 0, 'optical' => 0, 'notified' => 0], $result);
        Notification::assertNothingSent();
    }

    public function test_repeat_scan_is_suppressed_by_cooldown(): void
    {
        User::factory()->create(['role' => 'owner']);
        OntDevice::factory()->create([
            'serial_number' => 'COOLDOWNONT01',
            'connection_status' => 'offline',
            'rx_power_quality' => 'good',
        ]);

        $this->assertSame(1, $this->service->scan()['notified']);
        $this->assertSame(0, $this->service->scan()['notified']);

        Notification::assertSentToTimes(
            User::query()->first(),
            AsnetNotification::class,
            1
        );
    }

    public function test_scan_is_skipped_when_alerts_are_disabled(): void
    {
        User::factory()->create(['role' => 'owner']);
        config()->set('genieacs.alerts.enabled', false);
        OntDevice::factory()->create(['connection_status' => 'offline']);

        $result = $this->service->scan();

        $this->assertSame(['offline' => 0, 'optical' => 0, 'notified' => 0], $result);
        Notification::assertNothingSent();
    }

    public function test_batch_limit_caps_the_number_of_alerted_devices(): void
    {
        User::factory()->create(['role' => 'owner']);
        config()->set('genieacs.alerts.batch_limit', 2);
        OntDevice::factory()->count(4)->create(['connection_status' => 'offline']);

        $result = $this->service->scan();

        $this->assertSame(2, $result['offline']);
        $this->assertSame(2, $result['notified']);
    }
}
