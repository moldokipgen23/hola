<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\ScheduleBooking;
use App\Models\TransportRoute;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\Models\VehicleType;
use App\Services\OrderWorkflowService;
use App\Services\TripWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationWiringTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
            'role' => 'owner',
        ]);

        $this->customer = User::create([
            'name' => 'Customer',
            'email' => 'customer-'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
            'role' => 'customer',
        ]);
    }

    public function test_new_order_notifies_the_business_owner(): void
    {
        [$business, $product] = $this->shoppingBusiness();
        $business->update(['created_by' => $this->owner->id]);

        $this->postJson("/api/businesses/{$business->slug}/orders", $this->orderPayload($product))->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'new_order',
        ]);
    }

    public function test_order_status_change_notifies_owner_and_customer(): void
    {
        [$business, $product] = $this->shoppingBusiness();
        $business->update(['created_by' => $this->owner->id]);

        $response = $this->actingAs($this->customer)->postJson(
            "/api/businesses/{$business->slug}/orders",
            $this->orderPayload($product),
        )->assertCreated();

        $orderId = $response->json('order.id');
        $business->update(['created_by' => $this->owner->id]);

        app(OrderWorkflowService::class)->transition(
            Order::find($orderId),
            'confirmed',
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'order_confirmed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'type' => 'order_confirmed',
        ]);
    }

    public function test_new_trip_notifies_owner_and_status_change_notifies_owner_and_customer(): void
    {
        [$business, $vehicle] = $this->transportBusiness();
        $business->update(['created_by' => $this->owner->id]);

        $response = $this->actingAs($this->customer)->postJson(
            "/api/businesses/{$business->slug}/trips",
            $this->tripPayload($vehicle),
        )->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'new_trip',
        ]);

        $tripId = $response->json('trip.id');

        app(TripWorkflowService::class)->transition(
            Trip::find($tripId),
            'confirmed',
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'trip_confirmed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'type' => 'trip_confirmed',
        ]);
    }

    public function test_seat_booking_and_confirmation_notify_owner_and_customer(): void
    {
        $business = $this->verifiedTransportBusiness();
        $business->update(['created_by' => $this->owner->id]);
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(1)->toDateString());

        $response = $this->actingAs($this->customer)->postJson(
            "/api/transport/schedules/{$schedule->id}/book",
            [
                'seat_labels' => ['A1'],
                'customer_name' => 'Test Customer',
                'customer_phone' => '9000000000',
            ],
        )->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'new_seat_booking',
        ]);

        $bookingId = $response->json('booking.id');
        ScheduleBooking::find($bookingId)->markConfirmed();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'type' => 'seat_booking_confirmed',
        ]);
    }

    public function test_vehicle_rental_booking_notifies_owner(): void
    {
        $business = $this->verifiedTransportBusiness();
        $business->update(['created_by' => $this->owner->id]);
        $vehicle = $this->vehicle($business, serviceMode: 'rental');
        $vehicle->update(['price_per_day' => 500]);

        $this->actingAs($this->customer)->postJson(
            "/api/transport/rentals/vehicles/{$vehicle->id}/book",
            [
                'start_date' => now()->addDays(1)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'customer_name' => 'Test Customer',
                'customer_phone' => '9000000000',
                'terms_accepted' => true,
            ],
        )->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'type' => 'new_vehicle_rental',
        ]);
    }

    public function test_customer_can_view_own_order_detail(): void
    {
        [$business, $product] = $this->shoppingBusiness();

        $response = $this->actingAs($this->customer)->postJson(
            "/api/businesses/{$business->slug}/orders",
            $this->orderPayload($product),
        )->assertCreated();

        $orderId = $response->json('order.id');

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/my-orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('order.id', $orderId)
            ->assertJsonPath('order.items.0.name', $product->name);

        $other = User::create([
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
        ]);
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/my-orders/{$orderId}")
            ->assertForbidden();
    }

    public function test_phone_lookup_returns_anonymous_order_with_reference(): void
    {
        [$business, $product] = $this->shoppingBusiness();

        $this->postJson("/api/businesses/{$business->slug}/orders", array_merge(
            $this->orderPayload($product),
            ['client_reference' => 'anon-order-ref-123'],
        ))->assertCreated();

        $this->getJson('/api/orders/lookup?phone=9000000000&client_reference=anon-order-ref-123')
            ->assertOk()
            ->assertJsonPath('order.client_reference', 'anon-order-ref-123');

        $this->getJson('/api/orders/lookup?phone=9000000000')
            ->assertUnprocessable();

        $this->getJson('/api/orders/lookup?phone=9999999999&client_reference=anon-order-ref-123')
            ->assertNotFound();
    }

    private function shoppingBusiness(): array
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
            'price' => 100,
            'stock' => 10,
            'availability' => 'in_stock',
            'is_active' => true,
        ]);

        return [$business, $product];
    }

    private function orderPayload(Product $product): array
    {
        return [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name' => 'Test Customer',
            'customer_phone' => '9000000000',
            'delivery_method' => 'pickup',
        ];
    }

    private function transportBusiness(): array
    {
        $category = Category::where('slug', 'transport')->firstOrFail();
        $suffix = str()->lower(str()->random(8));
        $business = Business::create([
            'category_id' => $category->id,
            'name' => "Transport Business {$suffix}",
            'slug' => "transport-business-{$suffix}",
            'address' => 'Test address',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'pincode' => '795128',
            'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => [
                'catalog' => false,
                'orders' => false,
                'bookings' => false,
                'inventory' => false,
                'transport' => true,
                'turf' => false,
            ],
        ]);

        $vehicle = Vehicle::create([
            'business_id' => $business->id,
            'name' => "Test Vehicle {$suffix}",
            'type' => 'car',
            'service_mode' => 'taxi',
            'seats' => 4,
            'base_fare' => 100,
            'fare_per_km' => 10,
            'min_km' => 1,
            'availability_status' => 'available',
            'is_active' => true,
        ]);

        return [$business, $vehicle];
    }

    private function tripPayload(Vehicle $vehicle): array
    {
        return [
            'vehicle_id' => $vehicle->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '9000000000',
            'pickup_location' => 'Pickup point',
            'drop_location' => 'Drop point',
            'seats_required' => 1,
        ];
    }

    private function verifiedTransportBusiness(): Business
    {
        $category = Category::create(['name' => 'Taxi', 'slug' => 'taxi-transport-notif', 'module_type' => 'booking']);

        return Business::create([
            'name' => 'Lamka Travels',
            'slug' => 'lamka-travels-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Main Rd',
            'verification_status' => 'verified',
            'enabled_modules' => ['transport' => true],
        ]);
    }

    private function vehicle(Business $business, string $serviceMode = 'bus'): Vehicle
    {
        $type = VehicleType::create(['name' => 'Bus', 'slug' => 'bus-notif-'.uniqid(), 'is_active' => true]);

        return Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Express Bus',
            'type' => $type->slug,
            'service_mode' => $serviceMode,
            'seats' => 6,
            'seat_layout' => [
                ['label' => 'A1', 'row' => 1, 'col' => 1, 'deck' => 'lower', 'type' => 'window'],
                ['label' => 'A2', 'row' => 1, 'col' => 2, 'deck' => 'lower', 'type' => 'aisle'],
            ],
        ]);
    }

    private function schedule(Business $business, Vehicle $vehicle, string $date): VehicleSchedule
    {
        $route = TransportRoute::create([
            'origin' => 'Lamka', 'destination' => 'Aizawl', 'distance_km' => 250, 'base_fare' => 400,
        ]);

        return VehicleSchedule::create([
            'business_id' => $business->id,
            'vehicle_id' => $vehicle->id,
            'transport_route_id' => $route->id,
            'origin' => 'Lamka',
            'destination' => 'Aizawl',
            'departure_date' => $date,
            'departure_time' => '08:00',
            'seats_capacity' => $vehicle->seats,
            'price' => 450,
            'status' => 'scheduled',
        ]);
    }
}
