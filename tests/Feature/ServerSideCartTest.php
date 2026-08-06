<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServerSideCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_and_view_cart_with_server_pricing(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 125.50, stock: 10);

        $add = $this->withHeader('X-Guest-Token', 'guest-abc')
            ->postJson("/api/businesses/{$business->slug}/cart/items", [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('cart.item_count', 2)
            ->assertJsonPath('cart.subtotal', '251.00')
            ->assertJsonPath('cart.items.0.unit_price', '125.50');

        $cartId = $add->json('cart.cart_id');

        $this->withHeader('X-Guest-Token', 'guest-abc')
            ->getJson("/api/businesses/{$business->slug}/cart")
            ->assertOk()
            ->assertJsonPath('cart.cart_id', $cartId)
            ->assertJsonPath('cart.item_count', 2);
    }

    public function test_cart_subtotal_is_recomputed_from_live_price(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 10);

        $this->withHeader('X-Guest-Token', 'guest-price')
            ->postJson("/api/businesses/{$business->slug}/cart/items", [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->assertCreated();

        $product->update(['price' => 150]);

        $this->withHeader('X-Guest-Token', 'guest-price')
            ->getJson("/api/businesses/{$business->slug}/cart")
            ->assertOk()
            ->assertJsonPath('cart.subtotal', '150.00')
            ->assertJsonPath('cart.items.0.unit_price', '150.00');
    }

    public function test_guest_carts_are_isolated_by_token(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 10);

        $this->withHeader('X-Guest-Token', 'guest-one')
            ->postJson("/api/businesses/{$business->slug}/cart/items", [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->assertCreated();

        $this->withHeader('X-Guest-Token', 'guest-two')
            ->getJson("/api/businesses/{$business->slug}/cart")
            ->assertOk()
            ->assertJsonPath('cart.item_count', 0);
    }

    public function test_update_quantity_and_cart_is_persisted(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 10);

        $this->withHeader('X-Guest-Token', 'guest-update')
            ->postJson("/api/businesses/{$business->slug}/cart/items", [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->assertCreated();

        $this->withHeader('X-Guest-Token', 'guest-update')
            ->putJson("/api/businesses/{$business->slug}/cart/items/{$product->id}", [
                'quantity' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('cart.item_count', 4);

        $this->withHeader('X-Guest-Token', 'guest-update')
            ->getJson("/api/businesses/{$business->slug}/cart")
            ->assertOk()
            ->assertJsonPath('cart.item_count', 4);
    }

    public function test_remove_item_clears_cart(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 10);

        $this->withHeader('X-Guest-Token', 'guest-remove')
            ->postJson("/api/businesses/{$business->slug}/cart/items", [
                'product_id' => $product->id,
                'quantity' => 2,
            ])->assertCreated();

        $this->withHeader('X-Guest-Token', 'guest-remove')
            ->deleteJson("/api/businesses/{$business->slug}/cart/items/{$product->id}")
            ->assertOk()
            ->assertJsonPath('cart.item_count', 0);
    }

    public function test_cannot_add_out_of_stock_or_unorderable_product(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 5);
        $product->update(['stock' => 0, 'availability' => 'out_of_stock']);

        $this->withHeader('X-Guest-Token', 'guest-oo')
            ->postJson("/api/businesses/{$business->slug}/cart/items", [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_quantity_cannot_exceed_stock(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 3, price: 100);

        $this->withHeader('X-Guest-Token', 'guest-stock')
            ->postJson("/api/businesses/{$business->slug}/cart/items", [
                'product_id' => $product->id,
                'quantity' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_delivery_subtotal_checks_minimum_order(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 10);
        $area = Area::where('slug', 'lamka')->firstOrFail();

        DeliveryZone::create([
            'business_id' => $business->id,
            'area_id' => $area->id,
            'delivery_fee' => 20,
            'min_order_amount' => 300,
            'is_active' => true,
        ]);

        $this->withHeader('X-Guest-Token', 'guest-min')
            ->postJson("/api/businesses/{$business->slug}/cart/items", [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->assertCreated();

        $this->withHeader('X-Guest-Token', 'guest-min')
            ->getJson("/api/businesses/{$business->slug}/cart?delivery_method=delivery&area_id={$area->id}")
            ->assertOk()
            ->assertJsonPath('cart.checkout_ready', false)
            ->assertJsonPath('cart.delivery.deliverable', false)
            ->assertJsonPath('cart.delivery.min_order_amount', 300);
    }

    public function test_workflow_stamps_tracking_timestamps(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 10);
        $order = $this->placeOrder($product, delivery: true);

        $workflow = app(OrderWorkflowService::class);

        foreach (['confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered'] as $status) {
            $workflow->transition($order, $status);
        }

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->confirmed_at);
        $this->assertNotNull($fresh->preparing_at);
        $this->assertNotNull($fresh->ready_at);
        $this->assertNotNull($fresh->out_for_delivery_at);
        $this->assertNotNull($fresh->delivered_at);
    }

    public function test_tracking_timeline_marks_reached_steps(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 10);
        $order = $this->placeOrder($product, delivery: true);

        app(OrderWorkflowService::class)->transition($order, 'confirmed');
        app(OrderWorkflowService::class)->transition($order, 'preparing');

        $timeline = $order->fresh()->trackingTimeline();

        $statuses = collect($timeline['timeline'])->pluck('status')->all();
        $this->assertSame(['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered'], $statuses);
        $this->assertSame('preparing', $timeline['current_status']);

        $reached = collect($timeline['timeline'])->filter(fn ($s) => $s['reached'])->pluck('status')->all();
        $this->assertSame(['pending', 'confirmed', 'preparing'], $reached);
        $this->assertNull(collect($timeline['timeline'])->firstWhere('status', 'delivered')['at']);
    }

    public function test_terminal_status_short_circuits_timeline(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 10);
        $order = $this->placeOrder($product);

        app(OrderWorkflowService::class)->transition($order, 'cancelled');

        $timeline = $order->fresh()->trackingTimeline();

        $this->assertSame('cancelled', $timeline['current_status']);
        $this->assertCount(1, $timeline['timeline']);
        $this->assertSame('cancelled', $timeline['timeline'][0]['status']);
        $this->assertNotNull($timeline['timeline'][0]['at']);
    }

    public function test_customer_can_track_own_order(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 10);
        $customer = $this->customer();
        $orderId = $this->placeOrder($product, customer: $customer)->id;

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/my-orders/{$orderId}/track")
            ->assertOk()
            ->assertJsonPath('tracking.current_status', 'pending');
    }

    private function placeOrder($product, bool $delivery = false, $customer = null)
    {
        $customer ??= $this->customer();
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name' => 'Track Customer',
            'customer_phone' => '9000000000',
        ];

        if ($delivery) {
            $area = Area::where('slug', 'lamka')->firstOrFail();
            DeliveryZone::firstOrCreate(
                ['business_id' => $product->business->id, 'area_id' => $area->id],
                ['delivery_fee' => 20, 'pincodes' => ['795128'], 'is_active' => true],
            );
            $payload['delivery_method'] = 'delivery';
            $payload['delivery_address'] = 'Some address';
            $payload['pincode'] = '795128';
        } else {
            $payload['delivery_method'] = 'pickup';
        }

        $response = $this->actingAs($customer, 'sanctum')->postJson(
            "/api/businesses/{$product->business->slug}/orders",
            $payload,
        )->assertCreated();

        return Order::find($response->json('order.id'));
    }

    private function customer()
    {
        return User::create([
            'name' => 'Track Customer',
            'email' => 'track-'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    private function deliveredOrder($product)
    {
        $order = $this->placeOrder($product, delivery: true);
        $workflow = app(OrderWorkflowService::class);
        foreach (['confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered'] as $status) {
            $workflow->transition($order, $status);
        }
        $order->update(['payment_status' => 'paid', 'payment_method' => 'cash']);

        return $order;
    }

    public function test_refund_paid_delivered_order_records_transaction(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 5);
        $order = $this->deliveredOrder($product);

        app(OrderWorkflowService::class)->refund($order, 'Customer requested');

        $fresh = $order->fresh();
        $this->assertSame('refunded', $fresh->status);
        $this->assertSame('refunded', $fresh->payment_status);
        $this->assertNotNull($fresh->refunded_at);

        $this->assertDatabaseHas('transactions', [
            'billable_type' => Order::class,
            'billable_id' => $order->id,
            'type' => 'refund',
            'amount' => 120,
            'status' => 'completed',
        ]);
    }

    public function test_refund_restores_released_inventory(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 5);
        $order = $this->deliveredOrder($product);

        $this->assertSame(4, $product->fresh()->stock);

        app(OrderWorkflowService::class)->refund($order);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_cannot_refund_unpaid_or_undelivered_order(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 5);
        $order = $this->placeOrder($product);

        $this->expectException(ValidationException::class);
        app(OrderWorkflowService::class)->refund($order);
    }

    public function test_refund_is_idempotent_via_transaction_guard(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 10);
        $order = $this->deliveredOrder($product);

        app(OrderWorkflowService::class)->refund($order);

        $this->expectException(ValidationException::class);
        app(OrderWorkflowService::class)->refund($order);
    }

    public function test_owner_can_refund_via_api(): void
    {
        [$business, $product] = $this->shoppingBusiness(price: 100, stock: 10);
        $owner = $this->owner();
        $business->update(['created_by' => $owner->id]);
        $order = $this->deliveredOrder($product);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/businesses/{$business->id}/orders/{$order->id}/refund", [
                'reason' => 'Defective item',
            ])
            ->assertOk()
            ->assertJsonPath('order.status', 'refunded')
            ->assertJsonPath('order.payment_status', 'refunded');
    }

    private function owner(): User
    {
        return User::create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
            'role' => 'owner',
        ]);
    }

    private function shoppingBusiness(int $stock, float $price = 100): array
    {
        Pincode::updateOrCreate(
            ['pincode' => '795128', 'locality' => 'Test Locality'],
            [
                'district' => 'Churachandpur',
                'state' => 'Manipur',
                'serviceable' => true,
            ],
        );

        $category = Category::where('slug', 'shopping-retail')->firstOrFail();
        $suffix = str()->lower(str()->random(8));
        $business = Business::create([
            'category_id' => $category->id,
            'name' => "Test Shop {$suffix}",
            'slug' => "test-shop-{$suffix}",
            'address' => 'Test address',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'pincode' => '795128',
            'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => [
                'catalog' => true,
                'orders' => true,
                'bookings' => false,
                'inventory' => true,
                'transport' => false,
                'turf' => false,
            ],
        ]);

        $product = Product::create([
            'business_id' => $business->id,
            'name' => "Test Product {$suffix}",
            'slug' => "test-product-{$suffix}",
            'price' => $price,
            'stock' => $stock,
            'availability' => 'in_stock',
            'is_active' => true,
        ]);

        return [$business, $product];
    }
}
