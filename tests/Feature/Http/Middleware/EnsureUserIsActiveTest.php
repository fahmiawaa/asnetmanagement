<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_access_authenticated_page(): void
    {
        $user = User::factory()->create([
            'active' => true,
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk();
    }

    public function test_inactive_user_is_logged_out_and_redirected_to_login(): void
    {
        $user = User::factory()->create([
            'active' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHasErrors([
                'email' => 'Akun Anda sudah dinonaktifkan.',
            ]);
        $this->assertGuest();
    }
}
