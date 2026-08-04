<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\Order;
use App\Models\User;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, string $moduleType): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'module_type' => $moduleType,
            'is_canonical' => true,
            'is_active' => true,
        ]);
    }

    private function business(string $name, array $modules): Business
    {
        return Business::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'category_id' => $this->category($name.' Cat', 'ordering')->id,
            'address' => 'Test street',
            'enabled_modules' => $modules,
        ]);
    }

    private function order(Business $business, string $number): Order
    {
        return Order::create([
            'business_id' => $business->id,
            'order_number' => $number,
            'customer_name' => 'Test Customer',
            'customer_phone' => '9876543210',
            'total' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public function test_orders_page_filters_by_business_type(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        FeatureFlag::where('key', 'world.shop')->firstOrFail()->update(['is_enabled' => true]);
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $shop = $this->business('Grocery Hub', ['catalog' => true, 'orders' => true]);
        $booking = $this->business('Salon Appoint', ['bookings' => true]);
        $taxi = $this->business('City Cabs', ['transport' => true]);

        $shopOrder = $this->order($shop, 'SHOP-001');
        $bookingOrder = $this->order($booking, 'BOOK-001');
        $taxiOrder = $this->order($taxi, 'TAXI-001');

        $this->actingAs($admin)
            ->get(route('admin.orders', ['type' => 'shopping']))
            ->assertOk()
            ->assertSee('SHOP-001')
            ->assertDontSee('BOOK-001')
            ->assertDontSee('TAXI-001');

        $this->actingAs($admin)
            ->get(route('admin.orders', ['type' => 'booking']))
            ->assertOk()
            ->assertSee('BOOK-001')
            ->assertDontSee('SHOP-001')
            ->assertDontSee('TAXI-001');

        $this->actingAs($admin)
            ->get(route('admin.orders', ['type' => 'taxi']))
            ->assertOk()
            ->assertSee('TAXI-001')
            ->assertDontSee('SHOP-001')
            ->assertDontSee('BOOK-001');

        $this->assertNotNull($shopOrder->id);
        $this->assertNotNull($bookingOrder->id);
        $this->assertNotNull($taxiOrder->id);
    }
}
