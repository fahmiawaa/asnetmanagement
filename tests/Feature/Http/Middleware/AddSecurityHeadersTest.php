<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;

class AddSecurityHeadersTest extends TestCase
{
    public function test_web_response_includes_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader(
                'Referrer-Policy',
                'strict-origin-when-cross-origin'
            )
            ->assertHeader(
                'Permissions-Policy',
                'camera=(), microphone=()'
            );
    }

    public function test_https_production_response_includes_hsts(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->withServerVariables(['HTTPS' => 'on'])
            ->get('https://example.test/login')
            ->assertOk()
            ->assertHeader(
                'Strict-Transport-Security',
                'max-age=31536000'
            );
    }

    public function test_non_https_response_does_not_include_hsts(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get('/login')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}
