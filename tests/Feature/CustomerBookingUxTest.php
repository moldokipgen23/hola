<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_booking_detail(): void
    {
        $user = User::factory()->create();
        [$business, $service, $booking] = $this->flexibleBooking($user);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/my-bookings/'.$booking->id)
            ->assertOk()
            ->assertJsonPath('booking.id', $booking->id)
            ->assertJsonPath('booking.customer_name', 'UX Customer');
    }

    public function test_customer_can_reschedule_flexible_booking(): void
    {
        $user = User::factory()->create();
        [$business, $service, $booking] = $this->flexibleBooking($user);
        $target = now()->addDays(3)->toDateString();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/my-bookings/'.$booking->id.'/reschedule', [
                'to_date' => $target,
                'to_time' => '16:30',
            ])
            ->assertOk()
            ->assertJsonPath('booking.status', 'confirmed');

        $fresh = $booking->fresh();
        $this->assertSame($target, $fresh->booking_date->toDateString());
        $this->assertSame('16:30', $fresh->start_time->format('H:i'));
        $this->assertSame('confirmed', $fresh->status);
        $this->assertNotNull($fresh->metadata['previous_schedule'] ?? null);
    }

    public function test_reschedule_rejects_past_dates(): void
    {
        $user = User::factory()->create();
        [$business, $service, $booking] = $this->flexibleBooking($user);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/my-bookings/'.$booking->id.'/reschedule', [
                'to_date' => today()->subDay()->toDateString(),
                'to_time' => '10:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to_date');
    }

    public function test_reschedule_moves_slot_booking_and_blocks_double_booked_slot(): void
    {
        $user = User::factory()->create();
        [$business, $service, $booking] = $this->flexibleBooking($user);
        $target = now()->addDays(2);
        $slot = $service->timeSlots()->create([
            'day_of_week' => $target->dayOfWeek,
            'start_time' => '15:00',
            'end_time' => '16:00',
            'capacity' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/my-bookings/'.$booking->id.'/reschedule', [
                'to_date' => $target->toDateString(),
                'to_slot_id' => $slot->id,
            ])
            ->assertOk()
            ->assertJsonPath('booking.time_slot_id', $slot->id);

        $this->assertSame($target->toDateString(), $booking->fresh()->booking_date->toDateString());

        Booking::create([
            'business_id' => $business->id,
            'service_id' => $service->id,
            'time_slot_id' => $slot->id,
            'booking_type' => 'time_slot',
            'booking_date' => $target,
            'start_time' => '15:00',
            'end_time' => '16:00',
            'duration_minutes' => 60,
            'party_size' => 1,
            'customer_name' => 'Other',
            'customer_phone' => '9000000099',
            'unit_price' => 250,
            'total_price' => 250,
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);

        $second = $this->flexibleBooking($user)[2];
        $this->actingAs($user, 'sanctum')
            ->putJson('/api/my-bookings/'.$second->id.'/reschedule', [
                'to_date' => $target->toDateString(),
                'to_slot_id' => $slot->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to_slot_id');
    }

    public function test_phone_lookup_returns_anonymous_bookings_with_reference(): void
    {
        [$business, $service, $booking] = $this->flexibleBooking();
        $booking->update(['client_reference' => 'anon-ref-123']);

        $this->getJson('/api/bookings/lookup?phone='.$booking->customer_phone.'&client_reference=anon-ref-123')
            ->assertOk()
            ->assertJsonPath('booking.id', $booking->id);

        $this->getJson('/api/bookings/lookup?phone='.$booking->customer_phone)
            ->assertUnprocessable();

        $this->getJson('/api/bookings/lookup?phone=9999999999&client_reference=anon-ref-123')
            ->assertNotFound();
    }

    private function flexibleBooking(?User $user = null): array
    {
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
            'name' => "UX Biz {$suffix}",
            'slug' => "ux-biz-{$suffix}",
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
            'name' => 'Appointment',
            'price' => 250,
            'duration' => 60,
            'capacity' => 1,
            'advance_booking_days' => 30,
            'cancellation_hours' => 2,
            'is_active' => true,
        ]);
        $booking = Booking::create([
            'business_id' => $business->id,
            'service_id' => $service->id,
            'booking_type' => 'standard',
            'booking_date' => now()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'duration_minutes' => 60,
            'party_size' => 1,
            'user_id' => $user?->id,
            'customer_name' => 'UX Customer',
            'customer_phone' => '9000000077',
            'customer_email' => 'ux@example.test',
            'unit_price' => 250,
            'total_price' => 250,
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return [$business, $service, $booking];
    }
}
