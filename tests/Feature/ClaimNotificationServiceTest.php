<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Services\ClaimNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_phone_handles_indian_formats(): void
    {
        $service = app(ClaimNotificationService::class);

        $this->assertSame('919876543210', $service->normalizePhone('9876543210'));
        $this->assertSame('919876543210', $service->normalizePhone('09876543210'));
        $this->assertSame('919876543210', $service->normalizePhone('+91 98765 43210'));
        $this->assertSame('917005453422', $service->normalizePhone('070054 53422'));
        $this->assertNull($service->normalizePhone(null));
        $this->assertNull($service->normalizePhone(''));
    }

    public function test_template_renders_business_placeholders(): void
    {
        $business = $this->business();
        $service = app(ClaimNotificationService::class);

        $rendered = $service->renderTemplate(
            'Hi {business_name}! Claim your listing at {claim_url} on {site_name}.',
            $business,
        );

        $this->assertStringContainsString($business->name, $rendered);
        $this->assertStringContainsString('claim', $rendered);
        $this->assertStringContainsString('Eiho One', $rendered);
    }

    public function test_send_with_no_gateway_logs_failed_attempt(): void
    {
        $business = $this->business();
        $service = app(ClaimNotificationService::class);

        $result = $service->send($business, 'Test message', 'whatsapp');

        $this->assertFalse($result['sent']);
        $this->assertDatabaseHas('notification_logs', [
            'business_id' => $business->id,
            'type' => 'claim_invitation',
            'status' => 'failed',
        ]);
    }

    public function test_autopilot_sends_nothing_when_disabled(): void
    {
        \App\Models\Setting::set('autopilot_claim_enabled', '0');
        $business = $this->business();

        $this->artisan('autopilot:claim-notifications')
            ->expectsOutputToContain('DISABLED')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notification_logs', 0);
    }

    private function business(): Business
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'T', 'district' => 'Churachandpur', 'state' => 'Manipur', 'serviceable' => true,
        ]);
        $cat = Category::updateOrCreate(['slug' => 'food-restaurants'], [
            'name' => 'Food & Restaurants', 'module_type' => 'ordering', 'is_active' => true,
        ]);
        $suffix = str()->lower(str()->random(6));

        return Business::create([
            'category_id' => $cat->id,
            'name' => 'Test Biz '.$suffix, 'slug' => 'test-biz-'.$suffix,
            'address' => 'x', 'district' => 'Churachandpur', 'state' => 'Manipur',
            'pincode' => '795128', 'phone' => '9876543210', 'is_active' => true,
            'claim_status' => 'unclaimed', 'source' => 'import',
        ]);
    }
}
