<?php

namespace Tests\Feature\Http\Controllers;

use App\Services\SystemHealthService;
use Mockery;
use Tests\TestCase;

class HealthCheckControllerTest extends TestCase
{
    public function test_health_endpoint_requires_configured_token(): void
    {
        config(['operations.health_token' => 'monitor-secret']);

        $this->getJson('/health')->assertUnauthorized();
    }

    public function test_health_endpoint_returns_503_for_critical_report(): void
    {
        config(['operations.health_token' => 'monitor-secret']);
        $health = Mockery::mock(SystemHealthService::class);
        $health->shouldReceive('check')->once()->andReturn([
            'status' => 'critical',
            'checked_at' => now()->toIso8601String(),
            'checks' => [
                'backup' => [
                    'label' => 'Backup',
                    'status' => 'critical',
                    'message' => 'Backup sukses belum ditemukan.',
                    'value' => null,
                ],
            ],
        ]);
        $this->app->instance(SystemHealthService::class, $health);

        $this->withHeader('X-ASNet-Health-Token', 'monitor-secret')
            ->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'critical')
            ->assertJsonPath('checks.backup.status', 'critical');
    }
}
