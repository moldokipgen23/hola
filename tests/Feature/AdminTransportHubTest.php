<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\Models\VehicleType;
use App\Services\LaunchControlService;
use App\Services\TransportAvailabilityService;
use App\Services\VehicleRentalService;
use Database\Seeders\LaunchPhase1Seeder;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTransportHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_confirm_and_complete_seat_booking(): void
    {
        $admin = $this->admin();
        [$business, $schedule] = $this->seatBookingFixture();

        $booking = app(TransportAvailabilityService::class)->bookSeats(
            $schedule,
            ['1'],
            ['name' => 'Alice', 'phone' => '9000000001'],
        );

        $this->actingAs($admin)
            ->put(route('admin.seat-bookings.status', $booking->id), ['status' => 'confirmed'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $booking->fresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.seat-bookings.status', $booking->id), ['status' => 'completed'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_admin_can_cancel_seat_booking_with_reason(): void
    {
        $admin = $this->admin();
        [$business, $schedule] = $this->seatBookingFixture();

        $booking = app(TransportAvailabilityService::class)->bookSeats(
            $schedule,
            ['1'],
            ['name' => 'Alice', 'phone' => '9000000001'],
        );

        $this->actingAs($admin)
            ->put(route('admin.seat-bookings.status', $booking->id), [
                'status' => 'cancelled',
                'cancellation_reason' => 'Bus cancelled',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('Bus cancelled', $booking->cancellation_reason);
    }

    public function test_admin_can_mark_seat_booking_cash_collected(): void
    {
        $admin = $this->admin();
        [$business, $schedule] = $this->seatBookingFixture();

        $booking = app(TransportAvailabilityService::class)->bookSeats(
            $schedule,
            ['1'],
            ['name' => 'Alice', 'phone' => '9000000001'],
        );

        $this->actingAs($admin)
            ->put(route('admin.seat-bookings.payment-status', $booking->id), ['payment_status' => 'paid'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    public function test_admin_cannot_mark_cancelled_seat_booking_paid(): void
    {
        $admin = $this->admin();
        [$business, $schedule] = $this->seatBookingFixture();

        $booking = app(TransportAvailabilityService::class)->bookSeats(
            $schedule,
            ['1'],
            ['name' => 'Alice', 'phone' => '9000000001'],
        );
        $booking->markCancelled();

        $this->actingAs($admin)
            ->from(route('admin.seat-bookings'))
            ->put(route('admin.seat-bookings.payment-status', $booking->id), ['payment_status' => 'paid'])
            ->assertSessionHasErrors();
    }

    public function test_admin_can_confirm_and_complete_vehicle_rental(): void
    {
        $admin = $this->admin();
        [$business, $vehicle] = $this->rentalFixture();

        $rental = app(VehicleRentalService::class)->book(
            $vehicle,
            now()->addDays(2),
            now()->addDays(4),
            ['name' => 'Alice', 'phone' => '9000000001'],
        );

        $this->actingAs($admin)
            ->put(route('admin.vehicle-rentals.status', $rental->id), ['status' => 'confirmed'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $rental->fresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.vehicle-rentals.status', $rental->id), ['status' => 'completed'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('completed', $rental->fresh()->status);
    }

    public function test_admin_can_cancel_vehicle_rental_with_reason(): void
    {
        $admin = $this->admin();
        [$business, $vehicle] = $this->rentalFixture();

        $rental = app(VehicleRentalService::class)->book(
            $vehicle,
            now()->addDays(2),
            now()->addDays(4),
            ['name' => 'Alice', 'phone' => '9000000001'],
        );

        $this->actingAs($admin)
            ->put(route('admin.vehicle-rentals.status', $rental->id), [
                'status' => 'cancelled',
                'cancellation_reason' => 'Customer cancelled',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $rental->refresh();
        $this->assertSame('cancelled', $rental->status);
        $this->assertSame('Customer cancelled', $rental->cancellation_reason);
    }

    public function test_admin_can_mark_vehicle_rental_cash_collected(): void
    {
        $admin = $this->admin();
        [$business, $vehicle] = $this->rentalFixture();

        $rental = app(VehicleRentalService::class)->book(
            $vehicle,
            now()->addDays(2),
            now()->addDays(4),
            ['name' => 'Alice', 'phone' => '9000000001'],
        );

        $this->actingAs($admin)
            ->put(route('admin.vehicle-rentals.payment-status', $rental->id), ['payment_status' => 'paid'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('paid', $rental->fresh()->payment_status);
    }

    public function test_transport_hub_page_shows_status_actions(): void
    {
        $admin = $this->admin();
        [$business, $schedule] = $this->seatBookingFixture();

        $booking = app(TransportAvailabilityService::class)->bookSeats(
            $schedule,
            ['1'],
            ['name' => 'Alice', 'phone' => '9000000001'],
        );

        $this->actingAs($admin)
            ->get(route('admin.transport-bookings', ['tab' => 'seat_bookings']))
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('Cash Collected');

        $this->assertNotNull($booking->id);
    }

    private function admin(): User
    {
        $this->seed(LaunchPhase1Seeder::class);
        $this->seed(WorldSeeder::class);
        foreach (FeatureFlag::where('key', 'like', 'world.%')->get() as $flag) {
            $flag->update(['is_enabled' => true]);
        }
        LaunchControlService::clearCache();

        return User::factory()->create(['role' => 'super_admin']);
    }

    private function verifiedTransportBusiness(): Business
    {
        $category = Category::create(['name' => 'Taxi', 'slug' => 'taxi-transport-admin-'.uniqid(), 'module_type' => 'booking']);

        return Business::create([
            'name' => 'Lamka Travels',
            'slug' => 'lamka-travels-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Main Rd',
            'verification_status' => 'verified',
            'enabled_modules' => ['transport' => true],
        ]);
    }

    private function seatBookingFixture(): array
    {
        $business = $this->verifiedTransportBusiness();
        $type = VehicleType::create(['name' => 'Bus', 'slug' => 'bus-admin-'.uniqid(), 'is_active' => true]);
        $vehicle = Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Express Bus',
            'type' => $type->slug,
            'service_mode' => 'bus',
            'seats' => 6,
        ]);
        $route = TransportRoute::create([
            'origin' => 'Lamka', 'destination' => 'Aizawl', 'distance_km' => 200, 'base_fare' => 400,
        ]);
        $schedule = VehicleSchedule::create([
            'business_id' => $business->id,
            'vehicle_id' => $vehicle->id,
            'transport_route_id' => $route->id,
            'origin' => 'Lamka',
            'destination' => 'Aizawl',
            'departure_date' => now()->addDays(2)->toDateString(),
            'departure_time' => '08:00',
            'seats_capacity' => $vehicle->seats,
            'price' => 450,
            'status' => 'scheduled',
        ]);

        return [$business, $schedule];
    }

    private function rentalFixture(): array
    {
        $business = $this->verifiedTransportBusiness();
        $type = VehicleType::create(['name' => 'Bolero', 'slug' => 'bolero-admin-'.uniqid(), 'is_active' => true]);
        $vehicle = Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Bolero SUV',
            'type' => $type->slug,
            'service_mode' => 'rental',
            'seats' => 6,
            'price_per_day' => 800,
            'is_active' => true,
            'availability_status' => 'available',
        ]);

        return [$business, $vehicle];
    }
}
