<?php

namespace Tests\Feature\Services;

use App\Models\MikrotikDevice;
use App\Services\MikrotikConnectionService;
use Mockery;
use RouterOS\Client;
use RouterOS\Query;
use RuntimeException;
use Tests\TestCase;

class MikrotikIsolationBatchTest extends TestCase
{
    public function test_disables_secret_disconnects_session_and_verifies_result(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('query')
            ->once()
            ->with('/ppp/secret/print')
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([
                ['.id' => '*1', 'name' => 'user-1', 'disabled' => 'false'],
            ]);
        $client->shouldReceive('query')
            ->once()
            ->with('/ppp/active/print')
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([
                ['.id' => '*A', 'name' => 'user-1'],
            ]);
        $client->shouldReceive('query')
            ->once()
            ->with(Mockery::type(Query::class))
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([]);
        $client->shouldReceive('query')
            ->once()
            ->with(Mockery::type(Query::class))
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([]);
        $client->shouldReceive('query')
            ->once()
            ->with('/ppp/secret/print')
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([
                ['.id' => '*1', 'name' => 'user-1', 'disabled' => 'true'],
            ]);
        $client->shouldReceive('query')
            ->once()
            ->with('/ppp/active/print')
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([]);
        $service = $this->serviceUsing($client);

        $results = $service->isolatePppoeBatch(
            new MikrotikDevice(['name' => 'Router Uji']),
            ['user-1'],
            2
        );

        $this->assertSame('executed', $results['user-1']['status']);
        $this->assertSame(1, $results['user-1']['disconnected']);
        $this->assertNull($results['user-1']['error']);
    }

    public function test_circuit_breaker_stops_commands_after_failure_threshold(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('query')
            ->once()
            ->with('/ppp/secret/print')
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([
                ['.id' => '*1', 'name' => 'user-1', 'disabled' => 'false'],
                ['.id' => '*2', 'name' => 'user-2', 'disabled' => 'false'],
                ['.id' => '*3', 'name' => 'user-3', 'disabled' => 'false'],
                ['.id' => '*4', 'name' => 'user-4', 'disabled' => 'false'],
            ]);
        $client->shouldReceive('query')
            ->once()
            ->with('/ppp/active/print')
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([]);
        $client->shouldReceive('query')
            ->twice()
            ->with(Mockery::type(Query::class))
            ->ordered()
            ->andThrow(new RuntimeException('RouterOS write gagal.'));
        $client->shouldReceive('query')
            ->once()
            ->with('/ppp/secret/print')
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([]);
        $client->shouldReceive('query')
            ->once()
            ->with('/ppp/active/print')
            ->ordered()
            ->andReturnSelf();
        $client->shouldReceive('read')
            ->once()
            ->ordered()
            ->andReturn([]);
        $service = $this->serviceUsing($client);
        $device = new MikrotikDevice([
            'name' => 'Router Uji',
        ]);

        $results = $service->isolatePppoeBatch(
            $device,
            ['user-1', 'user-2', 'user-3', 'user-4'],
            2
        );

        $this->assertSame('command_failed', $results['user-1']['status']);
        $this->assertSame('command_failed', $results['user-2']['status']);
        $this->assertSame('circuit_open', $results['user-3']['status']);
        $this->assertSame('circuit_open', $results['user-4']['status']);
    }

    private function serviceUsing(Client $client): MikrotikConnectionService
    {
        return new class($client) extends MikrotikConnectionService
        {
            public function __construct(private readonly Client $fakeClient) {}

            public function connect(MikrotikDevice $device): Client
            {
                return $this->fakeClient;
            }
        };
    }
}
