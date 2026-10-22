<?php

namespace Tests\Feature\Services;

use App\Models\GenieacsServer;
use App\Services\GenieAcsTaskService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GenieAcsTaskServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GenieacsServer $server;

    private GenieAcsTaskService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = GenieacsServer::factory()->create([
            'base_url' => 'http://10.0.0.5:7557',
            'username' => 'api-user',
            'encrypted_password' => 'api-secret',
        ]);
        $this->service = app(GenieAcsTaskService::class);
    }

    public function test_summon_sends_connection_request_and_treats_202_as_accepted(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*commands=1*' => Http::response(['id' => 'task-42'], 202)]);

        $result = $this->service->summon($this->server, 'aaaa-bbbb-cccc');

        $this->assertTrue($result['accepted']);
        $this->assertSame(202, $result['status_code']);
        $this->assertSame('task-42', $result['remote_task_id']);
        $this->assertNull($result['error']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/devices/aaaa-bbbb-cccc?commands=1')
                && $request['name'] === 'ConnectionRequest'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_reboot_sends_reboot_command(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*commands=1*' => Http::response('', 200)]);

        $result = $this->service->reboot($this->server, 'device-1');

        $this->assertTrue($result['accepted']);
        Http::assertSent(fn ($request): bool => $request['name'] === 'Reboot');
    }

    public function test_set_parameter_posts_task_and_hides_value_from_error(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/tasks*' => Http::response('task rejected: rahasia-baru', 400)]);

        $result = $this->service->setParameter(
            $this->server,
            'device-1',
            'VirtualParameters.WlanPassword',
            'rahasia-baru',
        );

        $this->assertFalse($result['accepted']);
        $this->assertSame(400, $result['status_code']);
        $this->assertStringNotContainsString('rahasia-baru', (string) $result['error']);
        $this->assertStringContainsString('***', (string) $result['error']);
    }

    public function test_set_parameter_uses_configured_body_template(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/tasks*' => Http::response(['id' => 'task-9'], 202)]);

        $result = $this->service->setParameter(
            $this->server,
            'device-1',
            'InternetGatewayDevice.WiFi.SSID.1.SSID',
            'ASNet-Baru',
        );

        $this->assertTrue($result['accepted']);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/tasks')
                && $request['objectName'] === 'InternetGatewayDevice.WiFi.SSID.1.SSID'
                && $request['value'] === 'ASNet-Baru';
        });
    }

    public function test_read_parameter_refuses_denylisted_path(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        try {
            $this->service->readParameter($this->server, 'device-1', 'VirtualParameters.WlanPassword');
            $this->fail('Denylist pembacaan tidak diterapkan.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('denylist', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_read_parameter_unwraps_value_wrapper(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/devices*' => Http::response([
                'InternetGatewayDevice' => [
                    'WiFi' => [
                        'SSID' => ['1' => ['SSID' => ['_value' => 'ASNet-Lama']]],
                    ],
                ],
            ], 200),
        ]);

        $value = $this->service->readParameter(
            $this->server,
            'device-1',
            'InternetGatewayDevice.WiFi.SSID.1.SSID',
        );

        $this->assertSame('ASNet-Lama', $value);
    }

    public function test_read_last_inform_parses_timestamp(): void
    {
        Http::preventStrayRequests();
        $stamp = '2026-10-02T10:20:30.000Z';
        Http::fake(['*/devices*' => Http::response(['_lastInform' => $stamp], 200)]);

        $lastInform = $this->service->readLastInform($this->server, 'device-1');

        $this->assertNotNull($lastInform);
        $this->assertTrue($lastInform->equalTo(CarbonImmutable::parse($stamp)));
    }

    public function test_non_success_response_is_reported_as_rejected(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*commands=1*' => Http::response('boom', 503)]);

        $result = $this->service->summon($this->server, 'device-1');

        $this->assertFalse($result['accepted']);
        $this->assertSame(503, $result['status_code']);
        $this->assertSame('boom', $result['error']);
        $this->assertNull($result['remote_task_id']);
    }
}
