<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\SubscriptionPlan;
use App\Services\MonetizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_uses_business_override(): void
    {
        $business = $this->business();
        $business->update(['commission_percent' => 5]);
        SubscriptionPlan::create([
            'name' => 'Free', 'slug' => 'free', 'price' => 0, 'commission_percent' => 0, 'is_active' => true,
        ]);

        $this->assertSame(5.0, app(MonetizationService::class)->commissionPercentFor($business));
    }

    public function test_commission_uses_active_subscription_plan_rate(): void
    {
        $business = $this->business();
        $plan = SubscriptionPlan::create([
            'name' => 'Premium', 'slug' => 'premium', 'price' => 999, 'commission_percent' => 3, 'is_active' => true,
        ]);
        app(MonetizationService::class)->subscribe($business, $plan);

        $this->assertSame(3.0, app(MonetizationService::class)->commissionPercentFor($business));
    }

    public function test_record_commission_is_idempotent(): void
    {
        $business = $this->business();
        $business->update(['commission_percent' => 10]);
        $service = app(MonetizationService::class);

        $first = $service->recordCommission($business, Order::class, 123, 1000);
        $second = $service->recordCommission($business, Order::class, 123, 1000);

        $this->assertNotNull($first);
        $this->assertSame(100.0, (float) $first->amount);
        $this->assertNull($second, 'A second commission for the same billable must be a no-op.');
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_zero_commission_records_nothing(): void
    {
        $business = $this->business();
        $service = app(MonetizationService::class);

        $this->assertNull($service->recordCommission($business, Order::class, 1, 500));
        $this->assertDatabaseCount('transactions', 0);
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
            'name' => 'Biz '.$suffix, 'slug' => 'biz-'.$suffix,
            'address' => 'x', 'district' => 'Churachandpur', 'state' => 'Manipur',
            'pincode' => '795128', 'phone' => '9000000001', 'is_active' => true,
        ]);
    }
}
