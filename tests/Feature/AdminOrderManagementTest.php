<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_order_detail(): void
    {
        [$admin, $order] = $this->setUpData();
        OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Rice Bag',
            'quantity' => 2,
            'unit_price' => 50,
            'total_price' => 100,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Rice Bag')
            ->assertSee('₹100.00');
    }

    public function test_admin_can_advance_order_status(): void
    {
        [$admin, $order] = $this->setUpData();

        $this->actingAs($admin)
            ->put(route('admin.orders.status', $order->id), ['status' => 'confirmed'])
            ->assertRedirect(route('admin.orders.show', $order->id));

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->confirmed_at);
    }

    public function test_admin_can_mark_payment_collected(): void
    {
        [$admin, $order] = $this->setUpData();
        $order->update(['status' => 'confirmed']);

        $this->actingAs($admin)
            ->put(route('admin.orders.payment-status', $order->id), ['payment_status' => 'paid'])
            ->assertRedirect(route('admin.orders.show', $order->id));

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_admin_can_refund_a_paid_delivered_order(): void
    {
        [$admin, $order] = $this->setUpData();
        $order->update(['status' => 'delivered', 'payment_status' => 'paid', 'delivered_at' => now()]);

        $this->actingAs($admin)
            ->put(route('admin.orders.refund', $order->id), ['reason' => 'Defective'])
            ->assertRedirect(route('admin.orders.show', $order->id));

        $order->refresh();
        $this->assertSame('refunded', $order->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame('Defective', $order->refund_reason);
    }

    public function test_invalid_status_transition_returns_error(): void
    {
        [$admin, $order] = $this->setUpData();
        $order->update(['status' => 'out_for_delivery']);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order->id))
            ->put(route('admin.orders.status', $order->id), ['status' => 'confirmed'])
            ->assertSessionHasErrors();
    }

    private function setUpData(): array
    {
        $this->seed(LaunchPhase1Seeder::class);
        $this->seed(WorldSeeder::class);
        foreach (FeatureFlag::where('key', 'like', 'world.%')->get() as $flag) {
            $flag->update(['is_enabled' => true]);
        }
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::where('slug', 'grocery')->firstOrFail();
        $business = Business::create([
            'name' => 'Grocery Hub',
            'slug' => 'grocery-hub-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Test street',
            'enabled_modules' => ['catalog' => true, 'orders' => true],
        ]);
        $order = Order::create([
            'business_id' => $business->id,
            'order_number' => 'SHOP-'.uniqid(),
            'customer_name' => 'Test Customer',
            'customer_phone' => '9876543210',
            'delivery_address' => '123 Test Lane',
            'total' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        return [$admin, $order];
    }
}
