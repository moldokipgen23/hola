<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\LaunchControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentKillSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_flipping_only_the_legacy_setting_cannot_enable_online_payments(): void
    {
        // Attacker/admin flips the legacy Setting on, but the platform master
        // switch (LaunchControlService `payments.online`) defaults OFF.
        Setting::set('payment_online_enabled', true);
        Setting::set('payment_razorpay_enabled', true);
        LaunchControlService::clearCache();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/payments/config')
            ->assertOk()
            ->assertJsonPath('payment_mode', 'offline')
            ->assertJsonPath('razorpay.enabled', false);
    }
}
