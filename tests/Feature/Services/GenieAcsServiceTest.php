<?php

namespace Tests\Feature\Services;

use App\Models\GenieacsServer;
use App\Services\GenieAcsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenieAcsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fetches_only_allowlisted_device_parameters_with_basic_auth(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://genieacs.test:7557/devices*' => Http::response([
                ['_id' => 'device-1'],
            ]),
        ]);
        $server = GenieacsServer::factory()->create([
            'base_url' => 'http://genieacs.test:7557/',
            'username' => 'nbi-reader',
            'encrypted_password' => 'secret-value',
        ]);

        $devices = app(GenieAcsService::class)->fetchDevices(
            $server,
            ['_id' => 'device-1'],
            25,
            50
        );

        $this->assertSame('device-1', $devices[0]['_id']);
        Http::assertSent(function (Request $request): bool {
            $projection = (string) data_get($request->data(), 'projection');

            return str_starts_with($request->url(), 'http://genieacs.test:7557/devices?')
                && data_get($request->data(), 'limit') === 25
                && data_get($request->data(), 'skip') === 50
                && str_contains($projection, 'VirtualParameters.RXPower')
                && ! str_contains(strtolower($projection), 'password')
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('nbi-reader:secret-value'));
        });
    }

    public function test_connection_failure_updates_safe_server_state(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://genieacs.test:7557/devices*' => Http::response([], 503),
        ]);
        $server = GenieacsServer::factory()->create([
            'base_url' => 'http://genieacs.test:7557',
        ]);

        $connected = app(GenieAcsService::class)->testConnection($server);

        $this->assertFalse($connected);
        $this->assertSame('failed', $server->fresh()->connection_status);
        $this->assertNotNull($server->fresh()->last_error_at);
    }
}
