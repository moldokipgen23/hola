<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure a clean limiter bucket so prior tests don't consume the allowance.
        $this->app->make('cache')->flush();
    }

    public function test_vendor_login_is_rate_limited_after_five_attempts(): void
    {
        // throttle:5,1 — five attempts allowed per minute, sixth blocked.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/vendor/login', [
                'email' => 'attacker@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/vendor/login', [
            'email' => 'attacker@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
