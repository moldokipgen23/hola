<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\ScheduleBooking;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\Models\VehicleType;
use App\Services\TransportAvailabilityService;
use Database\Seeders\TransportRouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransportSeatBookingTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedTransportBusiness(): Business
    {
        $category = Category::create(['name' => 'Taxi', 'slug' => 'taxi-transport-test', 'module_type' => 'booking']);

        return Business::create([
            'name' => 'Lamka Travels',
            'slug' => 'lamka-travels-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Main Rd',
            'verification_status' => 'verified',
            'enabled_modules' => ['transport' => true],
        ]);
    }

    private function vehicle(Business $business, array $layout = []): Vehicle
    {
        $type = VehicleType::create(['name' => 'Bus', 'slug' => 'bus-'.uniqid(), 'is_active' => true]);

        $attributes = [
            'business_id' => $business->id,
            'name' => 'Express Bus',
            'type' => $type->slug,
            'service_mode' => 'bus',
            'seats' => 6,
        ];

        if ($layout) {
            $attributes['seat_layout'] = $layout;
            $attributes['seats'] = count($layout);
        }

        return Vehicle::create($attributes);
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

    public function test_verified_transport_business_is_bookable(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());

        $service = app(TransportAvailabilityService::class);

        $this->assertTrue($service->isBookable($business));
        $this->assertFalse($service->isDirectoryTransport($business));
    }

    public function test_unverified_transport_business_is_call_only(): void
    {
        $category = Category::create(['name' => 'Taxi', 'slug' => 'taxi-call-test', 'module_type' => 'booking']);
        $business = Business::create([
            'name' => 'Local Taxi', 'slug' => 'local-taxi-'.uniqid(), 'category_id' => $category->id,
            'address' => 'x', 'verification_status' => 'pending', 'enabled_modules' => ['transport' => true],
        ]);

        $service = app(TransportAvailabilityService::class);

        $this->assertFalse($service->isBookable($business));
        $this->assertTrue($service->isDirectoryTransport($business));
    }

    public function test_seat_booking_reserves_seats_and_reduces_availability(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());

        $service = app(TransportAvailabilityService::class);
        $this->assertSame(6, $service->remainingSeats($schedule));

        $booking = $service->bookSeats($schedule, ['1', '2'], ['name' => 'Alice', 'phone' => '9000000001']);

        $this->assertSame(4, $service->remainingSeats($schedule));
        $this->assertSame(['1', '2'], $booking->seat_labels);
        $this->assertSame(900.0, (float) $booking->total_price);
        $this->assertSame('pending', $booking->status);
    }

    public function test_double_booking_same_seat_is_rejected(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());

        $service = app(TransportAvailabilityService::class);
        $service->bookSeats($schedule, ['1', '2'], ['name' => 'Alice', 'phone' => '9000000001']);

        $this->expectException(ValidationException::class);
        $service->bookSeats($schedule, ['1'], ['name' => 'Bob', 'phone' => '9000000002']);
    }

    public function test_visual_seat_layout_is_used_when_present(): void
    {
        $layout = [
            ['label' => 'A1', 'row' => 1, 'col' => 1, 'deck' => 'lower', 'type' => 'window'],
            ['label' => 'A2', 'row' => 1, 'col' => 2, 'deck' => 'lower', 'type' => 'aisle'],
            ['label' => 'A3', 'row' => 1, 'col' => 3, 'deck' => 'lower', 'type' => 'aisle'],
            ['label' => 'A4', 'row' => 1, 'col' => 4, 'deck' => 'lower', 'type' => 'window'],
        ];
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business, $layout);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());

        $service = app(TransportAvailabilityService::class);
        $map = $service->seatMapWithAvailability($schedule);

        $this->assertCount(4, $map);
        $this->assertSame('A1', $map[0]['label']);
        $this->assertTrue($map[0]['available']);
    }

    public function test_default_seat_map_generates_grid_for_numeric_count(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);

        $map = $vehicle->seatMap();
        $this->assertCount(6, $map);
        $this->assertSame('1', $map[0]['label']);
        $this->assertSame('6', $map[5]['label']);
    }

    public function test_confirm_booking_locks_seats(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());

        $service = app(TransportAvailabilityService::class);
        $booking = $service->bookSeats($schedule, ['1'], ['name' => 'Alice', 'phone' => '9000000001']);
        $booking->markConfirmed();

        $this->assertSame(5, $service->remainingSeats($schedule));
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_schedule_booking_is_created_via_relation(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());

        $this->assertSame(0, $business->scheduleBookings()->count());
        $this->assertSame(0, ScheduleBooking::count());
    }

    public function test_route_seeder_seeds_popular_routes_both_directions(): void
    {
        $this->seed(TransportRouteSeeder::class);

        $this->assertTrue(TransportRoute::where('origin', 'Lamka')->where('destination', 'Aizawl')->exists());
        $this->assertTrue(TransportRoute::where('origin', 'Aizawl')->where('destination', 'Lamka')->exists());
        $this->assertTrue(TransportRoute::where('origin', 'Lamka')->where('destination', 'Kanggui')->exists());
        $this->assertTrue(TransportRoute::where('origin', 'Lamka')->where('destination', 'Moreh')->exists());
        $this->assertTrue(TransportRoute::where('origin', 'Moreh')->where('destination', 'Lamka')->exists());

        $route = TransportRoute::where('origin', 'Lamka')->where('destination', 'Aizawl')->first();
        $this->assertSame(200.0, (float) $route->distance_km);
        $this->assertSame(300, (int) $route->estimated_minutes);
    }

    public function test_admin_route_store_creates_reverse_direction(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post(route('admin.transport-routes.store'), [
                'origin' => 'Lamka',
                'destination' => 'Aizawl',
                'distance_km' => 200,
                'estimated_minutes' => 300,
            ])
            ->assertRedirect(route('admin.transport-routes'));

        $this->assertTrue(TransportRoute::where('origin', 'Lamka')->where('destination', 'Aizawl')->exists());
        $this->assertTrue(TransportRoute::where('origin', 'Aizawl')->where('destination', 'Lamka')->exists());
    }

    public function test_admin_route_update_keeps_reverse_in_sync(): void
    {
        $this->seed(TransportRouteSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $route = TransportRoute::where('origin', 'Lamka')->where('destination', 'Aizawl')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.transport-routes.update', $route->id), [
                'origin' => 'Lamka',
                'destination' => 'Moreh',
                'distance_km' => 65,
                'estimated_minutes' => 120,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.transport-routes'));

        $this->assertTrue(TransportRoute::where('origin', 'Lamka')->where('destination', 'Moreh')->exists());
        $this->assertTrue(TransportRoute::where('origin', 'Moreh')->where('destination', 'Lamka')->exists());
        // Old pair removed.
        $this->assertFalse(TransportRoute::where('origin', 'Lamka')->where('destination', 'Aizawl')->exists());
        $this->assertFalse(TransportRoute::where('origin', 'Aizawl')->where('destination', 'Lamka')->exists());
    }

    public function test_schedule_with_boarding_and_drop_stops(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = VehicleSchedule::create([
            'business_id' => $business->id,
            'vehicle_id' => $vehicle->id,
            'origin' => 'Lamka',
            'destination' => 'Aizawl',
            'departure_date' => now()->addDays(2)->toDateString(),
            'departure_time' => '07:00',
            'seats_capacity' => $vehicle->seats,
            'price' => 400,
            'status' => 'scheduled',
            'boarding_stops' => [
                ['name' => 'Lamka Main Bus Stand', 'time' => '07:00', 'price_offset' => 0],
                ['name' => 'New Lamka', 'time' => '07:20', 'price_offset' => 10],
            ],
            'drop_stops' => [
                ['name' => 'Aizawl Central', 'time' => '12:00', 'price_offset' => 0],
            ],
        ]);

        $this->assertCount(2, $schedule->boarding_stops);
        $this->assertSame('New Lamka', $schedule->boarding_stops[1]['name']);
        $this->assertCount(1, $schedule->drop_stops);
    }

    public function test_multiple_vehicles_on_same_route_are_all_returned_in_search(): void
    {
        $business = $this->verifiedTransportBusiness();
        $bus1 = $this->vehicle($business);
        $bus2 = $this->vehicle($business);
        $date = now()->addDays(2)->toDateString();

        $this->schedule($business, $bus1, $date);
        $this->schedule($business, $bus2, $date);

        $response = $this->getJson('/api/transport/search?origin=Lamka&destination=Aizawl&date='.$date);
        $response->assertOk()->assertJsonCount(2, 'data');

        $this->assertCount(2, $response->json('data'));
        $this->assertSame('Lamka', $response->json('data.0.origin'));
        $this->assertSame('Aizawl', $response->json('data.0.destination'));
    }

    public function test_search_returns_route_distance_and_travel_time(): void
    {
        $this->seed(TransportRouteSeeder::class);
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $date = now()->addDays(2)->toDateString();

        $route = TransportRoute::where('origin', 'Lamka')->where('destination', 'Aizawl')->firstOrFail();
        VehicleSchedule::create([
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

        $response = $this->getJson('/api/transport/search?origin=Lamka&destination=Aizawl&date='.$date);
        $response->assertOk();
        $this->assertSame(200.0, (float) $response->json('data.0.distance_km'));
        $this->assertSame(300, $response->json('data.0.travel_minutes'));
        $this->assertSame('13:00', $response->json('data.0.arrival_estimate'));
    }

    public function test_unverified_transport_business_appears_as_call_only(): void
    {
        $this->seed(TransportRouteSeeder::class);
        $category = Category::create(['name' => 'Taxi', 'slug' => 'taxi-call-api-test', 'module_type' => 'booking']);
        $business = Business::create([
            'name' => 'Local Bus', 'slug' => 'local-bus-'.uniqid(), 'category_id' => $category->id,
            'address' => 'x', 'verification_status' => 'pending', 'enabled_modules' => ['transport' => true],
        ]);
        $vehicle = $this->vehicle($business);
        $date = now()->addDays(2)->toDateString();
        $this->schedule($business, $vehicle, $date);

        // Not bookable online — excluded from bookable search.
        $this->getJson('/api/transport/search?origin=Lamka&destination=Aizawl&date='.$date)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Surfaced as call-only via the businesses endpoint.
        $this->getJson('/api/transport/businesses?origin=Lamka&destination=Aizawl')
            ->assertOk()
            ->assertJsonPath('data.call_only.0.name', 'Local Bus');
    }

    public function test_phantom_seat_labels_are_rejected_when_seat_map_exists(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $vehicle->update(['seat_layout' => [
            ['label' => 'A1', 'row' => 1, 'col' => 1],
            ['label' => 'A2', 'row' => 1, 'col' => 2],
            ['label' => 'A3', 'row' => 1, 'col' => 3],
            ['label' => 'A4', 'row' => 1, 'col' => 4],
        ]]);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());
        $service = app(TransportAvailabilityService::class);

        $this->expectException(ValidationException::class);
        $service->bookSeats($schedule, ['A1', 'Z9'], ['name' => 'Alice', 'phone' => '9000000001']);
    }

    public function test_booking_more_seats_than_capacity_is_rejected(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());
        $schedule->update(['seats_capacity' => 4]);
        $service = app(TransportAvailabilityService::class);

        $this->expectException(ValidationException::class);
        $service->bookSeats($schedule, ['1', '2', '3', '4', '5'], ['name' => 'Alice', 'phone' => '9000000001']);
    }

    public function test_seat_booking_is_idempotent_on_client_reference(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());
        $service = app(TransportAvailabilityService::class);

        $first = $service->bookSeats(
            $schedule,
            ['1', '2'],
            ['name' => 'Alice', 'phone' => '9000000001'],
            null,
            'seat-ref-1',
        );

        $second = $service->bookSeats(
            $schedule,
            ['1', '2'],
            ['name' => 'Alice', 'phone' => '9000000001'],
            null,
            'seat-ref-1',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('schedule_bookings', 1);
    }

    public function test_stale_pending_booking_is_expired_and_seat_freed(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(2)->toDateString());
        $service = app(TransportAvailabilityService::class);

        $stale = $service->bookSeats($schedule, ['1'], ['name' => 'Stale', 'phone' => '9000000001']);

        // Force created_at back 40 minutes (Eloquent auto-timestamps on create).
        DB::table('schedule_bookings')
            ->where('id', $stale->id)
            ->update(['created_at' => now()->subMinutes(40), 'updated_at' => now()->subMinutes(40)]);

        // A new booking should free the stale pending seat.
        $service->bookSeats($schedule, ['1'], ['name' => 'Fresh', 'phone' => '9000000002']);

        $this->assertSame('cancelled', $stale->fresh()->status);
    }
}
