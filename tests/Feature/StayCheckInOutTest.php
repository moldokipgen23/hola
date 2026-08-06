<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StayCheckInOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_stay_booking_can_check_in_and_check_out(): void
    {
        [$business, $booking] = $this->confirmedStay();

        $workflow = app(BookingWorkflowService::class);
        $checkedIn = $workflow->checkIn($booking, '12B');

        $this->assertNotNull($checkedIn->checked_in_at);
        $this->assertSame('12B', $checkedIn->room_number);
        $this->assertSame('confirmed', $checkedIn->status);

        $checkedOut = $workflow->checkOut($checkedIn);

        $this->assertNotNull($checkedOut->checked_out_at);
        $this->assertSame('completed', $checkedOut->status);
    }

    public function test_only_confirmed_stays_can_check_in(): void
    {
        [$business, $booking] = $this->confirmedStay();

        $booking->update(['status' => 'pending', 'confirmed_at' => null]);

        $this->expectException(ValidationException::class);
        app(BookingWorkflowService::class)->checkIn($booking->fresh(), '12A');
    }

    public function test_check_out_requires_prior_check_in(): void
    {
        [$business, $booking] = $this->confirmedStay();

        $this->expectException(ValidationException::class);
        app(BookingWorkflowService::class)->checkOut($booking->fresh());
    }

    public function test_vendor_stay_board_renders_arrivals_and_in_house(): void
    {
        $owner = User::factory()->create();
        [$business, $booking] = $this->confirmedStay(owner: $owner);

        $workflow = app(BookingWorkflowService::class);
        $workflow->checkIn($booking->fresh(), '5');

        $this->actingAs($owner)
            ->get("/vendor/businesses/{$business->id}/stay-board")
            ->assertOk()
            ->assertSee($booking->customer_name);
    }

    private function confirmedStay(?User $owner = null): array
    {
        $owner = $owner ?? User::factory()->create();
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
            'name' => "Stay Biz {$suffix}",
            'slug' => "stay-biz-{$suffix}",
            'address' => 'Test address',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'pincode' => '795128',
            'phone' => '9876543210',
            'created_by' => $owner->id,
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
            'name' => 'Deluxe Room',
            'price' => 1000,
            'booking_mode' => 'stay',
            'price_unit' => 'night',
            'inventory_units' => 5,
            'advance_booking_days' => 30,
            'cancellation_hours' => 2,
            'is_active' => true,
        ]);
        $booking = Booking::create([
            'business_id' => $business->id,
            'service_id' => $service->id,
            'booking_type' => 'stay',
            'booking_date' => now(),
            'start_time' => '14:00',
            'end_time' => '11:00',
            'duration_minutes' => 1440,
            'customer_name' => 'Stay Guest',
            'customer_phone' => '9000000011',
            'check_in_date' => now(),
            'check_out_date' => now()->addDay(),
            'reservation_units' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return [$business, $booking];
    }
}
