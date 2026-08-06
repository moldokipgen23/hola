<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRental;
use App\Models\VehicleType;
use App\Services\VehicleRentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleRentalTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedTransportBusiness(): Business
    {
        $category = Category::create(['name' => 'Taxi', 'slug' => 'taxi-rental-test', 'module_type' => 'booking']);

        return Business::create([
            'name' => 'Lamka Rentals',
            'slug' => 'lamka-rentals-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Main Rd',
            'verification_status' => 'verified',
            'enabled_modules' => ['transport' => true],
        ]);
    }

    private function rentalVehicle(Business $business, int $pricePerDay = 800): Vehicle
    {
        $type = VehicleType::create(['name' => 'Bolero', 'slug' => 'bolero-'.uniqid(), 'is_active' => true]);

        return Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Bolero SUV',
            'type' => $type->slug,
            'service_mode' => 'rental',
            'seats' => 6,
            'price_per_day' => $pricePerDay,
            'is_active' => true,
            'availability_status' => 'available',
        ]);
    }

    public function test_rental_vehicle_is_available_without_conflicts(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->rentalVehicle($business);

        $service = app(VehicleRentalService::class);
        $from = now()->addDays(2);
        $to = now()->addDays(4);

        $this->assertTrue($service->isAvailable($vehicle, $from, $to));
        $this->assertSame(0, $service->conflictsFor($vehicle, $from, $to));
    }

    public function test_rental_booking_creates_and_blocks_overlap(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->rentalVehicle($business);

        $service = app(VehicleRentalService::class);
        $start = now()->addDays(2);
        $end = now()->addDays(4);

        $rental = $service->book($vehicle, $start, $end, ['name' => 'Alice', 'phone' => '9000000001']);

        $this->assertSame('pending', $rental->status);
        $this->assertSame(3, $rental->days);
        $this->assertSame(2400.0, (float) $rental->total_price);

        // Overlapping dates rejected.
        $this->expectException(ValidationException::class);
        $service->book($vehicle, now()->addDays(3), now()->addDays(5), ['name' => 'Bob', 'phone' => '9000000002']);
    }

    public function test_non_overlapping_rental_dates_allowed(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->rentalVehicle($business);

        $service = app(VehicleRentalService::class);
        $service->book($vehicle, now()->addDays(2), now()->addDays(4), ['name' => 'Alice', 'phone' => '9000000001']);

        // Different window, no overlap — should book fine.
        $second = $service->book($vehicle, now()->addDays(10), now()->addDays(11), ['name' => 'Bob', 'phone' => '9000000002']);
        $this->assertSame('pending', $second->status);
    }

    public function test_past_start_date_rejected(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->rentalVehicle($business);
        $service = app(VehicleRentalService::class);

        $this->expectException(ValidationException::class);
        $service->book($vehicle, now()->subDay(), now()->addDay(), ['name' => 'Alice', 'phone' => '9000000001']);
    }

    public function test_terms_required_when_vehicle_has_terms(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->rentalVehicle($business);
        $vehicle->update(['terms' => 'Deposit ₹1000 · 100 km/day · Fuel excluded']);
        $service = app(VehicleRentalService::class);

        // Rejected without acceptance.
        try {
            $service->book($vehicle, now()->addDays(2), now()->addDays(3), ['name' => 'Alice', 'phone' => '9000000001']);
            $this->fail('Should have required terms acceptance');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('terms_accepted', $e->errors());
        }

        // Accepted with flag set.
        $rental = $service->book($vehicle, now()->addDays(5), now()->addDays(6), ['name' => 'Alice', 'phone' => '9000000001'], false, true);
        $this->assertTrue($rental->terms_accepted);
    }

    public function test_admin_trip_actions_work(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->rentalVehicle($business);
        $trip = Trip::create([
            'business_id' => $business->id,
            'vehicle_id' => $vehicle->id,
            'customer_name' => 'Trip User',
            'customer_phone' => '9000000009',
            'pickup_location' => 'A',
            'drop_location' => 'B',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->put(route('admin.trips.status', $trip->id), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame('confirmed', $trip->fresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.trips.quote', $trip->id), ['fare' => 500])
            ->assertRedirect();
        $this->assertSame('500.00', (string) $trip->fresh()->fare);

        $this->actingAs($admin)
            ->put(route('admin.trips.payment-status', $trip->id), ['payment_status' => 'paid'])
            ->assertRedirect();
        $this->assertSame('paid', $trip->fresh()->payment_status);
    }

    public function test_vendor_rental_status_flow(): void
    {
        $business = $this->verifiedTransportBusiness();
        $vehicle = $this->rentalVehicle($business);
        $rental = VehicleRental::create([
            'business_id' => $business->id,
            'vehicle_id' => $vehicle->id,
            'customer_name' => 'Renter',
            'customer_phone' => '9000000003',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'price_per_day' => 800,
            'days' => 2,
            'total_price' => 1600,
            'status' => 'pending',
        ]);

        $owner = User::factory()->create(['role' => 'owner']);
        $business->update(['created_by' => $owner->id]);

        $this->actingAs($owner)
            ->put(route('vendor.rentals.status', $rental->id), ['status' => 'confirmed'])
            ->assertRedirect();
        $this->assertSame('confirmed', $rental->fresh()->status);
    }
}
