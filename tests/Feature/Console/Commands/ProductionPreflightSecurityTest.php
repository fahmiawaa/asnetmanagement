<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionPreflightSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_preflight_rejects_insecure_session_cookie(): void
    {
        User::factory()->create([
            'role' => 'owner',
            'active' => true,
        ]);
        config([
            'asnet.public_registration' => false,
            'session.secure' => false,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $this->artisan('asnet:preflight', ['--production' => true])
            ->expectsOutput('[FAIL] Session cookie hanya melalui HTTPS')
            ->assertFailed();
    }

    public function test_production_preflight_rejects_public_registration(): void
    {
        User::factory()->create([
            'role' => 'owner',
            'active' => true,
        ]);
        config([
            'asnet.public_registration' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $this->artisan('asnet:preflight', ['--production' => true])
            ->expectsOutput('[FAIL] Registrasi publik nonaktif')
            ->assertFailed();
    }
}
