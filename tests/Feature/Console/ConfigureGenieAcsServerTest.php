<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfigureGenieAcsServerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configures_and_tests_internal_genieacs_server(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://genieacs:7557/devices*' => Http::response([]),
        ]);

        $this->artisan('genieacs:configure', [
            'name' => 'GenieACS Utama',
            'baseUrl' => 'http://genieacs:7557',
        ])
            ->expectsOutputToContain('Konfigurasi GenieACS Utama tersimpan')
            ->expectsOutput('Koneksi NBI GenieACS berhasil.')
            ->assertSuccessful();

        $this->assertDatabaseHas('genieacs_servers', [
            'name' => 'GenieACS Utama',
            'base_url' => 'http://genieacs:7557',
            'connection_status' => 'connected',
            'is_active' => true,
        ]);
    }

    public function test_rejects_invalid_nbi_url(): void
    {
        Http::preventStrayRequests();

        $this->artisan('genieacs:configure', [
            'name' => 'Invalid',
            'baseUrl' => 'file:///etc/passwd',
        ])
            ->expectsOutputToContain('Nama atau URL NBI tidak valid')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }
}
