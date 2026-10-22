<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Notifications\AsnetNotification;
use App\Services\OperationalAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OperationalAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_is_sent_to_admin_and_webhook_only_once_during_cooldown(): void
    {
        Notification::fake();
        Http::fake(['alerts.example/*' => Http::response(['ok' => true])]);
        config([
            'cache.default' => 'array',
            'operations.alert.webhook_url' => 'https://alerts.example/asnet',
            'operations.alert.cooldown_minutes' => 30,
        ]);

        $owner = User::factory()->create([
            'role' => 'owner',
            'active' => true,
        ]);
        $service = app(OperationalAlertService::class);

        $this->assertTrue($service->send(
            'backup.failed',
            'Backup gagal',
            'S3 tidak tersedia.'
        ));
        $this->assertFalse($service->send(
            'backup.failed',
            'Backup gagal',
            'S3 tidak tersedia.'
        ));

        Notification::assertSentToTimes($owner, AsnetNotification::class, 1);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://alerts.example/asnet'
            && $request['key'] === 'backup.failed');

    }
}
