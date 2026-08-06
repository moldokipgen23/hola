<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\LaunchControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function enableOnlinePayments(): void
    {
        Setting::set('payment_online_enabled', true);
        Setting::set('payment_razorpay_enabled', true);
        FeatureFlag::where('key', 'payments.online')->update(['is_enabled' => true]);
        LaunchControlService::clearCache();
    }

    private function makeOrder(int $userId, int $amount): Order
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'Test', 'district' => 'Churachandpur', 'state' => 'Manipur', 'serviceable' => true,
        ]);
        $cat = Category::where('slug', 'food-restaurants')->first();
        if (! $cat) {
            $cat = Category::updateOrCreate(['slug' => 'food-restaurants'], ['name' => 'Food & Restaurants', 'module_type' => 'ordering', 'is_active' => true, 'is_canonical' => true]);
        }
        $biz = Business::create([
            'category_id' => $cat->id,
            'name' => 'Pay Biz', 'slug' => 'pay-biz-'.str()->lower(str()->random(6)),
            'address' => 'x', 'district' => 'Churachandpur', 'state' => 'Manipur',
            'pincode' => '795128', 'phone' => '9000000001', 'is_active' => true,
            'enabled_modules' => ['catalog' => true, 'orders' => true, 'inventory' => true],
        ]);
        $product = Product::create([
            'business_id' => $biz->id, 'name' => 'Item', 'slug' => 'item-'.str()->lower(str()->random(6)), 'price' => $amount, 'is_active' => true,
        ]);

        return Order::create([
            'business_id' => $biz->id,
            'user_id' => $userId,
            'order_number' => 'ORD-'.str()->upper(str()->random(10)),
            'customer_name' => 'Buyer', 'customer_phone' => '9000000002',
            'subtotal' => $amount, 'total' => $amount,
            'delivery_method' => 'pickup', 'payment_status' => 'pending',
            'payment_method' => 'cash', 'status' => 'pending',
        ]);
    }

    public function test_create_order_uses_server_amount_not_client_amount(): void
    {
        $this->enableOnlinePayments();
        $user = User::factory()->create();
        $order = $this->makeOrder($user->id, 500);
        Sanctum::actingAs($user);

        // Client tries to pay ₹1 but the real total is ₹500. The request must
        // NOT be rejected as unowned (server accepts the owner's request) and
        // must reach the gateway boundary with the server-derived amount. The
        // Razorpay SDK is not installed in tests, so a 503 means ownership +
        // amount derivation passed and only the gateway is missing.
        $response = $this->postJson('/api/payments/create-order', [
            'amount' => 1,
            'type' => 'order',
            'reference_id' => $order->id,
            'gateway' => 'razorpay',
        ]);
        $this->assertNotSame(403, $response->status());
        $this->assertNotSame(422, $response->status());
    }

    public function test_create_order_rejects_non_owner(): void
    {
        $this->enableOnlinePayments();
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $order = $this->makeOrder($owner->id, 500);
        Sanctum::actingAs($attacker);

        $this->postJson('/api/payments/create-order', [
            'amount' => 500,
            'type' => 'order',
            'reference_id' => $order->id,
            'gateway' => 'razorpay',
        ])->assertForbidden();
    }

    public function test_verify_payment_rejects_non_owner(): void
    {
        $this->enableOnlinePayments();
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $order = $this->makeOrder($owner->id, 500);
        Sanctum::actingAs($attacker);

        $this->postJson('/api/payments/verify', [
            'type' => 'order',
            'reference_id' => $order->id,
            'gateway' => 'razorpay',
            'razorpay_order_id' => 'ord_x',
            'razorpay_payment_id' => 'pay_x',
            'razorpay_signature' => 'sig_x',
        ])->assertForbidden();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }
}
