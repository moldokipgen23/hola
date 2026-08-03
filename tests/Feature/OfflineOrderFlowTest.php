<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfflineOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_order_uses_current_price_decrements_stock_and_is_idempotent(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 5, price: 125.50);

        $payload = $this->orderPayload($product, [
            'delivery_method' => 'pickup',
            'client_reference' => 'app-test-pickup-1',
        ]);

        $first = $this->postJson("/api/businesses/{$business->slug}/orders", $payload)
            ->assertCreated()
            ->assertJsonPath('payment_mode', 'offline')
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('order.payment_method', 'cash')
            ->assertJsonPath('order.payment_status', 'pending')
            ->assertJsonPath('order.subtotal', '251.00');

        $orderId = $first->json('order.id');
        $this->assertSame(3, $product->fresh()->stock);

        $this->postJson("/api/businesses/{$business->slug}/orders", $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('order.id', $orderId);

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_delivery_requires_a_matching_zone_and_minimum_order(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 10, price: 100);
        $area = Area::where('slug', 'lamka')->firstOrFail();

        DeliveryZone::create([
            'business_id' => $business->id,
            'area_id' => $area->id,
            'pincodes' => ['795128'],
            'min_order_amount' => 250,
            'delivery_fee' => 30,
            'estimated_minutes' => 45,
            'is_active' => true,
        ]);

        $payload = $this->orderPayload($product, [
            'delivery_method' => 'delivery',
            'delivery_address' => 'Test address',
            'pincode' => '795128',
            'client_reference' => 'delivery-minimum-1',
        ]);

        $this->postJson("/api/businesses/{$business->slug}/orders", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pincode');

        $payload['items'][0]['quantity'] = 3;
        $payload['client_reference'] = 'delivery-success-1';

        $this->postJson("/api/businesses/{$business->slug}/orders", $payload)
            ->assertCreated()
            ->assertJsonPath('order.delivery_fee', '30.00')
            ->assertJsonPath('order.total', '330.00')
            ->assertJsonPath('order.delivery_pincode', '795128');
    }

    public function test_radius_delivery_accepts_gps_when_within_business_radius(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: null, price: 80);
        $business->update([
            'latitude' => 24.3350,
            'longitude' => 93.7050,
            'delivery_radius_km' => 5,
        ]);

        $payload = $this->orderPayload($product, [
            'delivery_method' => 'delivery',
            'delivery_address' => 'Nearby address',
            'pincode' => '795128',
            'latitude' => 24.3360,
            'longitude' => 93.7060,
            'client_reference' => 'delivery-radius-1',
        ]);

        $this->postJson("/api/businesses/{$business->slug}/orders", $payload)
            ->assertCreated()
            ->assertJsonPath('order.delivery_fee', '0.00')
            ->assertJsonPath('order.customer_latitude', '24.3360000');

        $this->assertNull($product->fresh()->stock);
    }

    public function test_cancellation_restores_tracked_inventory_only_once(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 4, price: 50);
        $response = $this->postJson(
            "/api/businesses/{$business->slug}/orders",
            $this->orderPayload($product, [
                'delivery_method' => 'pickup',
                'client_reference' => 'cancel-stock-1',
            ]),
        )->assertCreated();

        $order = Order::findOrFail($response->json('order.id'));
        $this->assertSame(2, $product->fresh()->stock);

        $workflow = app(OrderWorkflowService::class);
        $cancelled = $workflow->transition($order, 'cancelled', 'Customer changed mind');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->inventory_released_at);
        $this->assertSame(4, $product->fresh()->stock);

        try {
            $workflow->transition($cancelled, 'cancelled');
            $this->fail('A second cancellation should be rejected.');
        } catch (ValidationException) {
            $this->assertSame(4, $product->fresh()->stock);
        }
    }

    public function test_workflow_rejects_skipped_transitions_and_payment_on_cancelled_order(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 5, price: 40);
        $response = $this->postJson(
            "/api/businesses/{$business->slug}/orders",
            $this->orderPayload($product, [
                'delivery_method' => 'pickup',
                'client_reference' => 'workflow-1',
            ]),
        )->assertCreated();

        $workflow = app(OrderWorkflowService::class);
        $order = Order::findOrFail($response->json('order.id'));

        $this->expectException(ValidationException::class);
        $workflow->transition($order, 'delivered');
    }

    public function test_cancelled_order_cannot_be_marked_as_cash_collected(): void
    {
        [$business, $product] = $this->shoppingBusiness(stock: 5, price: 40);
        $response = $this->postJson(
            "/api/businesses/{$business->slug}/orders",
            $this->orderPayload($product, [
                'delivery_method' => 'pickup',
                'client_reference' => 'cancelled-payment-1',
            ]),
        )->assertCreated();

        $workflow = app(OrderWorkflowService::class);
        $cancelled = $workflow->transition(
            Order::findOrFail($response->json('order.id')),
            'cancelled',
        );

        $this->expectException(ValidationException::class);
        $workflow->markCashCollected($cancelled);
    }

    public function test_online_payment_endpoints_are_dormant_but_cod_config_is_public(): void
    {
        $this->getJson('/api/payments/config')
            ->assertOk()
            ->assertJsonPath('payment_mode', 'offline')
            ->assertJsonPath('config.online_enabled', false)
            ->assertJsonPath('config.gateways.0', 'cod')
            ->assertJsonMissingPath('config.gateways.1');

        $this->postJson('/api/payments/create-order', [
            'amount' => 100,
            'type' => 'order',
            'reference_id' => 1,
        ])->assertServiceUnavailable()
            ->assertJsonPath('payment_mode', 'offline');
    }

    public function test_public_products_hide_inactive_and_non_catalog_businesses(): void
    {
        [$visibleBusiness, $visibleProduct] = $this->shoppingBusiness(stock: 2, price: 20);
        [$inactiveBusiness, $inactiveProduct] = $this->shoppingBusiness(stock: 2, price: 30);
        $inactiveBusiness->update(['is_active' => false]);
        [$directoryBusiness, $directoryProduct] = $this->shoppingBusiness(stock: 2, price: 40);
        $directoryBusiness->update([
            'enabled_modules' => [
                'catalog' => false,
                'orders' => false,
                'bookings' => false,
                'inventory' => false,
                'transport' => false,
                'turf' => false,
            ],
        ]);

        $response = $this->getJson('/api/products?per_page=100')->assertOk();
        $ids = collect($response->json('products.data'))->pluck('id');

        $this->assertTrue($ids->contains($visibleProduct->id));
        $this->assertFalse($ids->contains($inactiveProduct->id));
        $this->assertFalse($ids->contains($directoryProduct->id));
        $this->assertTrue($visibleBusiness->hasOrdersModule());
    }

    private function shoppingBusiness(?int $stock, float $price): array
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
            'whatsapp' => '9876543210',
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

    private function orderPayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'customer_name' => 'Test Customer',
            'customer_phone' => '9000000000',
            'customer_email' => 'customer@example.test',
        ], $overrides);
    }
}
