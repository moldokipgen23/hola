<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Service;
use App\Models\TimeSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StayAndSeatBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_stay_prices_nights_and_units_and_protects_inventory(): void
    {
        [$business, $room] = $this->bookableBusiness('hotels-lodges', [
            'booking_mode' => 'stay',
            'price' => 1000,
            'price_unit' => 'night',
            'inventory_units' => 2,
            'unit_label' => 'room',
            'min_stay_nights' => 1,
            'max_stay_nights' => 10,
        ]);
        $checkIn = now()->addDays(2)->toDateString();
        $checkOut = now()->addDays(5)->toDateString();

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($room, 'stay-1', [
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'reservation_units' => 2,
            'party_size' => 4,
        ]))->assertCreated()
            ->assertJsonPath('booking.booking_type', 'stay')
            ->assertJsonPath('booking.total_price', '6000.00')
            ->assertJsonPath('booking.payment_method', 'cash');

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($room, 'stay-2', [
            'check_in_date' => now()->addDays(4)->toDateString(),
            'check_out_date' => now()->addDays(6)->toDateString(),
            'reservation_units' => 1,
        ]))->assertUnprocessable()->assertJsonValidationErrors('reservation_units');

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($room, 'stay-3', [
            'check_in_date' => $checkOut,
            'check_out_date' => now()->addDays(6)->toDateString(),
            'reservation_units' => 2,
        ]))->assertCreated();
    }

    public function test_hotel_stay_requires_valid_checkout_and_respects_maximum_stay(): void
    {
        [$business, $room] = $this->bookableBusiness('hotels-lodges', [
            'booking_mode' => 'stay',
            'max_stay_nights' => 2,
        ]);
        $checkIn = now()->addDay()->toDateString();

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($room, 'stay-invalid-1', [
            'check_in_date' => $checkIn,
        ]))->assertUnprocessable()->assertJsonValidationErrors('check_out_date');

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($room, 'stay-invalid-2', [
            'check_in_date' => $checkIn,
            'check_out_date' => now()->addDays(5)->toDateString(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('check_out_date');
    }

    public function test_turf_slot_reserves_bookable_units_not_party_size(): void
    {
        [$business, $turf] = $this->bookableBusiness('sports-fitness', [
            'booking_mode' => 'slot',
            'has_fixed_slots' => true,
            'inventory_units' => 2,
        ]);
        $date = now()->addDays(2);
        $slot = $this->slot($turf, $date->dayOfWeek, 2);

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($turf, 'turf-1', [
            'booking_date' => $date->toDateString(),
            'time_slot_id' => $slot->id,
            'reservation_units' => 2,
            'party_size' => 12,
        ]))->assertCreated()->assertJsonPath('booking.booking_type', 'time_slot');

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($turf, 'turf-2', [
            'booking_date' => $date->toDateString(),
            'time_slot_id' => $slot->id,
            'reservation_units' => 1,
        ]))->assertUnprocessable()->assertJsonValidationErrors('time_slot_id');
    }

    public function test_fixed_seat_booking_enforces_capacity_and_prevents_duplicate_labels(): void
    {
        [$business, $seatService] = $this->bookableBusiness('sports-fitness', [
            'booking_mode' => 'seat',
            'price' => 250,
            'price_unit' => 'seat',
            'has_fixed_slots' => true,
        ]);
        $date = now()->addDays(3);
        $slot = $this->slot($seatService, $date->dayOfWeek, 3);

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($seatService, 'seat-1', [
            'booking_date' => $date->toDateString(),
            'time_slot_id' => $slot->id,
            'party_size' => 2,
            'seat_labels' => ['A1', 'A2'],
        ]))->assertCreated()
            ->assertJsonPath('booking.booking_type', 'seat')
            ->assertJsonPath('booking.total_price', '500.00');

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($seatService, 'seat-2', [
            'booking_date' => $date->toDateString(),
            'time_slot_id' => $slot->id,
            'party_size' => 1,
            'seat_labels' => ['a2'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('seat_labels');

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($seatService, 'seat-3', [
            'booking_date' => $date->toDateString(),
            'time_slot_id' => $slot->id,
            'party_size' => 2,
            'seat_labels' => ['B1', 'B2'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('time_slot_id');
    }

    private function bookableBusiness(string $categorySlug, array $serviceOverrides): array
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'Test Locality', 'district' => 'Churachandpur',
            'state' => 'Manipur', 'serviceable' => true,
        ]);
        $category = Category::where('slug', $categorySlug)->firstOrFail();
        $suffix = str()->lower(str()->random(8));
        $business = Business::create([
            'category_id' => $category->id,
            'name' => "Bookable {$suffix}", 'slug' => "bookable-{$suffix}",
            'address' => 'Test address', 'district' => 'Churachandpur',
            'state' => 'Manipur', 'pincode' => '795128', 'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => [
                'catalog' => false, 'orders' => false, 'bookings' => true,
                'inventory' => false, 'transport' => false,
                'turf' => ($serviceOverrides['booking_mode'] ?? null) === 'slot',
            ],
        ]);
        $service = Service::create(array_merge([
            'business_id' => $business->id,
            'name' => "Bookable service {$suffix}", 'price' => 500,
            'duration' => 60, 'capacity' => 1, 'inventory_units' => 1,
            'advance_booking_days' => 60, 'cancellation_hours' => 2,
            'has_fixed_slots' => false, 'booking_mode' => 'appointment',
            'is_active' => true,
        ], $serviceOverrides));

        return [$business, $service];
    }

    private function slot(Service $service, int $dayOfWeek, int $capacity): TimeSlot
    {
        return TimeSlot::create([
            'service_id' => $service->id, 'day_of_week' => $dayOfWeek,
            'start_time' => '14:00', 'end_time' => '15:00',
            'capacity' => $capacity, 'is_active' => true,
        ]);
    }

    private function payload(Service $service, string $reference, array $overrides): array
    {
        return array_merge([
            'service_id' => $service->id,
            'customer_name' => 'Booking Customer', 'customer_phone' => '9000000000',
            'client_reference' => $reference,
        ], $overrides);
    }
}
