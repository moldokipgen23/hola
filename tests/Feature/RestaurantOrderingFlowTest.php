<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Services\OrderWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantOrderingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_menu_item_availability_window_is_enforced_server_side(): void
    {
        Carbon::setTestNow('2026-07-22 08:00:00');
        [$business, $product] = $this->restaurant([
            'available_from' => '11:00',
            'available_until' => '22:00',
        ]);

        $this->postJson("/api/businesses/{$business->slug}/orders", $this->payload($product, 'menu-window-1'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        Carbon::setTestNow('2026-07-22 12:00:00');
        $this->postJson("/api/businesses/{$business->slug}/orders", $this->payload($product, 'menu-window-2'))
            ->assertCreated();
    }

    public function test_temporary_sold_out_state_is_enforced_and_exposed(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');
        [$business, $product] = $this->restaurant([
            'sold_out_until' => now()->addHours(2),
        ]);

        $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('product.is_orderable', false);

        $this->postJson("/api/businesses/{$business->slug}/orders", $this->payload($product, 'sold-out-1'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $product->update(['sold_out_until' => null]);
        $this->postJson("/api/businesses/{$business->slug}/orders", $this->payload($product, 'sold-out-2'))
            ->assertCreated();
    }

    public function test_order_uses_longest_menu_preparation_time(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');
        [$business, $first] = $this->restaurant(['preparation_minutes' => 15]);
        $second = Product::create([
            'business_id' => $business->id,
            'name' => 'Slow Dish',
            'slug' => 'slow-dish',
            'price' => 200,
            'availability' => 'in_stock',
            'preparation_minutes' => 35,
            'is_active' => true,
        ]);

        $payload = $this->payload($first, 'prep-time-1');
        $payload['items'][] = ['product_id' => $second->id, 'quantity' => 1];
        $response = $this->postJson("/api/businesses/{$business->slug}/orders", $payload)
            ->assertCreated();

        $order = Order::findOrFail($response->json('order.id'));
        $this->assertSame('12:35', $order->estimated_ready_at->format('H:i'));
    }

    public function test_pickup_and_delivery_have_distinct_ready_transitions(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');
        [$business, $product] = $this->restaurant();
        $response = $this->postJson(
            "/api/businesses/{$business->slug}/orders",
            $this->payload($product, 'pickup-flow-1'),
        )->assertCreated();

        $workflow = app(OrderWorkflowService::class);
        $order = Order::findOrFail($response->json('order.id'));
        $order = $workflow->transition($order, 'confirmed');
        $order = $workflow->transition($order, 'preparing');
        $order = $workflow->transition($order, 'ready');
        $order = $workflow->transition($order, 'delivered');

        $this->assertSame('delivered', $order->status);
    }

    private function restaurant(array $productOverrides = []): array
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'Test Locality',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'serviceable' => true,
        ]);
        $category = Category::where('slug', 'food-restaurants')->firstOrFail();
        $suffix = str()->lower(str()->random(8));
        $business = Business::create([
            'category_id' => $category->id,
            'name' => "Test Restaurant {$suffix}",
            'slug' => "test-restaurant-{$suffix}",
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
        $product = Product::create(array_merge([
            'business_id' => $business->id,
            'name' => "Test Dish {$suffix}",
            'slug' => "test-dish-{$suffix}",
            'menu_section' => 'Main Course',
            'food_type' => 'veg',
            'price' => 150,
            'stock' => null,
            'availability' => 'in_stock',
            'preparation_minutes' => 20,
            'is_active' => true,
        ], $productOverrides));

        return [$business, $product];
    }

    private function payload(Product $product, string $reference): array
    {
        return [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name' => 'Food Customer',
            'customer_phone' => '9000000000',
            'delivery_method' => 'pickup',
            'client_reference' => $reference,
        ];
    }
}
