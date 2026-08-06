<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Service;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorCalendarScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_reschedule_a_booking_from_the_portal(): void
    {
        [$owner, $business, $service] = $this->bookingBusiness();
        $booking = $this->placeBooking($business, $service, now()->addDays(2));

        $newDate = now()->addDays(4)->toDateString();

        $this->actingAs($owner)
            ->put(route('vendor.bookings.reschedule', $booking->id), [
                'rescheduled_to_date' => $newDate,
                'rescheduled_to_time' => '16:30',
                'reschedule_reason' => 'Vendor moved the slot',
            ])
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame($newDate, $booking->booking_date->toDateString());
        $this->assertSame('16:30', $booking->start_time->format('H:i'));
        $this->assertNotNull($booking->rescheduled_at);
        $this->assertSame('Vendor moved the slot', $booking->reschedule_reason);
        $this->assertSame($newDate, $booking->rescheduled_to_date->toDateString());
    }

    public function test_vendor_cannot_reschedule_another_owners_booking(): void
    {
        [$owner, $business, $service] = $this->bookingBusiness();
        $booking = $this->placeBooking($business, $service, now()->addDays(2));
        $other = User::factory()->create();

        $this->actingAs($other)
            ->put(route('vendor.bookings.reschedule', $booking->id), [
                'rescheduled_to_date' => now()->addDays(3)->toDateString(),
                'rescheduled_to_time' => '16:30',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_vendor_availability_calendar_shows_the_months_bookings(): void
    {
        [$owner, $business, $service] = $this->bookingBusiness();
        $this->placeBooking($business, $service, now()->addDay());

        $this->actingAs($owner)
            ->get(route('vendor.calendar', ['businessId' => $business->id]))
            ->assertOk()
            ->assertSee('Booking Customer');
    }

    public function test_vendor_can_edit_a_departure_schedule(): void
    {
        [$owner, $business] = $this->transportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(3)->toDateString());

        $edit = $this->actingAs($owner)
            ->get(route('vendor.schedules.edit', [$business->id, $schedule->id]))
            ->assertOk()
            ->assertSee('Edit Departure');

        $this->actingAs($owner)
            ->put(route('vendor.schedules.update', [$business->id, $schedule->id]), [
                'vehicle_id' => $vehicle->id,
                'transport_route_id' => $schedule->transport_route_id,
                'origin' => 'Lamka',
                'destination' => 'Aizawl',
                'departure_date' => now()->addDays(5)->toDateString(),
                'departure_time' => '09:30',
                'seats_capacity' => 4,
                'price' => 550,
                'notes' => 'Express service',
            ])
            ->assertRedirect(route('vendor.schedules', $business->id));

        $schedule->refresh();
        $this->assertSame('09:30', $schedule->departure_time);
        $this->assertSame(4, $schedule->seats_capacity);
        $this->assertSame('550.00', (string) $schedule->price);
        $this->assertSame('Express service', $schedule->notes);
    }

    public function test_vendor_cannot_edit_a_schedule_with_active_bookings(): void
    {
        [$owner, $business] = $this->transportBusiness();
        $vehicle = $this->vehicle($business);
        $schedule = $this->schedule($business, $vehicle, now()->addDays(3)->toDateString());

        $schedule->bookings()->create([
            'business_id' => $business->id,
            'customer_name' => 'Alice',
            'customer_phone' => '9000000001',
            'seat_labels' => ['1'],
            'seats' => 1,
            'party_size' => 1,
            'total_price' => 450,
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->get(route('vendor.schedules.edit', [$business->id, $schedule->id]))
            ->assertStatus(422);

        $this->actingAs($owner)
            ->put(route('vendor.schedules.update', [$business->id, $schedule->id]), [
                'vehicle_id' => $vehicle->id,
                'origin' => 'Lamka',
                'destination' => 'Aizawl',
                'departure_date' => now()->addDays(5)->toDateString(),
                'departure_time' => '09:30',
                'seats_capacity' => 4,
                'price' => 550,
            ])
            ->assertStatus(422);
    }

    private function placeBooking(Business $business, Service $service, Carbon $date): Booking
    {
        $response = $this->postJson("/api/businesses/{$business->slug}/bookings", [
            'service_id' => $service->id,
            'customer_name' => 'Booking Customer',
            'customer_phone' => '9000000000',
            'customer_email' => 'booking@example.test',
            'booking_date' => $date->toDateString(),
            'start_time' => '10:00',
            'client_reference' => 'vendor-op-'.str()->random(6),
        ])->assertCreated();

        return Booking::findOrFail($response->json('booking.id'));
    }

    private function bookingBusiness(): array
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'Test Locality',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'serviceable' => true,
        ]);
        $owner = User::factory()->create();
        $category = Category::where('slug', 'beauty-wellness')->firstOrFail();
        $suffix = str()->lower(str()->random(8));
        $business = Business::create([
            'created_by' => $owner->id,
            'category_id' => $category->id,
            'name' => "Booking Business {$suffix}",
            'slug' => "booking-business-{$suffix}",
            'address' => 'Test address',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'pincode' => '795128',
            'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => [
                'catalog' => false,
                'orders' => false,
                'bookings' => true,
                'inventory' => false,
                'transport' => false,
                'turf' => false,
            ],
        ]);
        $service = Service::create([
            'business_id' => $business->id,
            'name' => "Test Service {$suffix}",
            'price' => 250,
            'duration' => 60,
            'capacity' => 5,
            'advance_booking_days' => 30,
            'cancellation_hours' => 2,
            'has_fixed_slots' => false,
            'is_active' => true,
        ]);

        return [$owner, $business, $service];
    }

    private function transportBusiness(): array
    {
        $category = Category::create(['name' => 'Taxi', 'slug' => 'taxi-vendor-'.uniqid(), 'module_type' => 'booking']);
        $owner = User::factory()->create();
        $business = Business::create([
            'created_by' => $owner->id,
            'name' => 'Lamka Travels',
            'slug' => 'lamka-travels-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Main Rd',
            'verification_status' => 'verified',
            'enabled_modules' => ['transport' => true],
        ]);

        return [$owner, $business];
    }

    private function vehicle(Business $business): Vehicle
    {
        $type = VehicleType::create(['name' => 'Bus', 'slug' => 'bus-'.uniqid(), 'is_active' => true]);

        return Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Express Bus',
            'type' => $type->slug,
            'service_mode' => 'bus',
            'seats' => 6,
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
