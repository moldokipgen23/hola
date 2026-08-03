<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Services\BookingWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfflineBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_flexible_booking_derives_schedule_price_and_is_idempotent(): void
    {
        [$business, $service] = $this->bookingBusiness(capacity: 2, duration: 45, price: 350);
        $date = now()->addDay()->toDateString();
        $payload = $this->payload($service, $date, [
            'start_time' => '10:00',
            'client_reference' => 'booking-flex-1',
        ]);

        $first = $this->postJson("/api/businesses/{$business->slug}/bookings", $payload)
            ->assertCreated()
            ->assertJsonPath('payment_mode', 'offline')
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('booking.total_price', '350.00')
            ->assertJsonPath('booking.payment_method', 'cash');

        $booking = Booking::findOrFail($first->json('booking.id'));
        $this->assertSame(45, $booking->duration_minutes);
        $this->assertSame('10:00', $booking->start_time->format('H:i'));
        $this->assertSame('10:45', $booking->end_time->format('H:i'));

        $this->postJson("/api/businesses/{$business->slug}/bookings", $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('booking.id', $booking->id);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_flexible_capacity_is_scoped_to_service_and_party_size(): void
    {
        [$business, $service] = $this->bookingBusiness(capacity: 2);
        $date = now()->addDay()->toDateString();

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($service, $date, [
            'start_time' => '11:00',
            'party_size' => 2,
            'client_reference' => 'capacity-1',
        ]))->assertCreated();

        $this->postJson("/api/businesses/{$business->slug}/bookings", $this->payload($service, $date, [
            'start_time' => '11:30',
            'party_size' => 1,
            'client_reference' => 'capacity-2',
        ]))->assertUnprocessable()->assertJsonValidationErrors('start_time');
    }

    public function test_fixed_slot_enforces_weekday_capacity_and_server_schedule(): void
    {
        [$business, $service] = $this->bookingBusiness(fixed: true, capacity: 5, price: 500);
        $date = now()->addDays(2);
        $slot = TimeSlot::create([
            'service_id' => $service->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '14:00',
            'end_time' => '15:30',
            'capacity' => 3,
            'price_override' => 600,
            'is_active' => true,
        ]);

        $payload = $this->payload($service, $date->toDateString(), [
            'time_slot_id' => $slot->id,
            'party_size' => 2,
            'client_reference' => 'slot-1',
        ]);
        $this->postJson("/api/businesses/{$business->slug}/bookings", $payload)
            ->assertCreated()
            ->assertJsonPath('booking.total_price', '600.00');

        $booking = Booking::firstOrFail();
        $this->assertSame('14:00', $booking->start_time->format('H:i'));
        $this->assertSame(90, $booking->duration_minutes);

        $payload['client_reference'] = 'slot-2';
        $this->postJson("/api/businesses/{$business->slug}/bookings", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_slot_id');
    }

    public function test_slots_endpoint_only_returns_slots_for_selected_weekday(): void
    {
        [, $service] = $this->bookingBusiness(fixed: true);
        $date = now()->addDays(3);
        TimeSlot::create([
            'service_id' => $service->id,
            'day_of_week' => ($date->dayOfWeek + 1) % 7,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'capacity' => 1,
            'is_active' => true,
        ]);
        TimeSlot::create([
            'service_id' => $service->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'capacity' => 1,
            'is_active' => true,
        ]);

        $this->getJson("/api/services/{$service->id}/slots?date={$date->toDateString()}")
            ->assertOk()
            ->assertJsonCount(1, 'slots')
            ->assertJsonPath('slots.0.start_time', '10:00');
    }

    public function test_booking_workflow_rejects_skips_and_cancelled_payment(): void
    {
        [$business, $service] = $this->bookingBusiness();
        $response = $this->postJson(
            "/api/businesses/{$business->slug}/bookings",
            $this->payload($service, now()->addDay()->toDateString(), [
                'start_time' => '12:00',
                'client_reference' => 'workflow-booking-1',
            ]),
        )->assertCreated();

        $workflow = app(BookingWorkflowService::class);
        $booking = Booking::findOrFail($response->json('booking.id'));

        try {
            $workflow->transition($booking, 'completed');
            $this->fail('Pending bookings cannot skip confirmation.');
        } catch (ValidationException) {
            $this->assertSame('pending', $booking->fresh()->status);
        }

        $cancelled = $workflow->transition($booking, 'cancelled', 'Changed plans');
        $this->expectException(ValidationException::class);
        $workflow->markCashCollected($cancelled);
    }

    private function bookingBusiness(
        bool $fixed = false,
        int $capacity = 1,
        int $duration = 60,
        float $price = 250,
    ): array {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'Test Locality',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'serviceable' => true,
        ]);
        $category = Category::where('slug', 'beauty-wellness')->firstOrFail();
        $suffix = str()->lower(str()->random(8));
        $business = Business::create([
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
            'price' => $price,
            'duration' => $duration,
            'capacity' => $capacity,
            'advance_booking_days' => 30,
            'cancellation_hours' => 2,
            'has_fixed_slots' => $fixed,
            'is_active' => true,
        ]);

        return [$business, $service];
    }

    private function payload(Service $service, string $date, array $overrides = []): array
    {
        return array_merge([
            'service_id' => $service->id,
            'customer_name' => 'Booking Customer',
            'customer_phone' => '9000000000',
            'customer_email' => 'booking@example.test',
            'booking_date' => $date,
        ], $overrides);
    }
}
