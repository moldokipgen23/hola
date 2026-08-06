<?php

namespace Tests\Feature;

use App\Models\AvailabilityRule;
use App\Models\BookableResource;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Service;
use App\Services\SlotGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSlotGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_rules_generate_slots_with_buffers_and_durations(): void
    {
        [$business, $service, $resource] = $this->resourceService();
        $date = now()->addDays(2);
        AvailabilityRule::create([
            'resource_id' => $resource->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'slot_duration_minutes' => 60,
            'buffer_minutes' => 30,
            'capacity' => 5,
            'is_active' => true,
        ]);

        $slots = app(SlotGenerationService::class)->slotsFor($service, $date);

        $this->assertCount(2, $slots, '10:00-11:00 and 11:30-12:30 with 30min buffers');
        $this->assertSame('10:00', $slots[0]['start_time']);
        $this->assertSame('11:30', $slots[1]['start_time']);
        $this->assertSame(5, $slots[0]['capacity']);
        $this->assertTrue($slots[0]['can_accommodate']);
    }

    public function test_blackout_dates_are_excluded(): void
    {
        [$business, $service, $resource] = $this->resourceService();
        $date = now()->addDays(2);
        AvailabilityRule::create([
            'resource_id' => $resource->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'slot_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'capacity' => 5,
            'blackout_dates' => [$date->toDateString()],
            'is_active' => true,
        ]);

        $slots = app(SlotGenerationService::class)->slotsFor($service, $date);

        $this->assertEmpty($slots, 'Blackout date must suppress all slots.');
    }

    public function test_bookings_consume_resource_capacity(): void
    {
        [$business, $service, $resource] = $this->resourceService();
        $date = now()->addDays(2);
        AvailabilityRule::create([
            'resource_id' => $resource->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'slot_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'capacity' => 3,
            'is_active' => true,
        ]);

        Booking::create([
            'business_id' => $business->id,
            'service_id' => $service->id,
            'time_slot_id' => null,
            'booking_type' => 'time_slot',
            'customer_name' => 'Tester',
            'customer_phone' => '9000000001',
            'booking_date' => $date,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'duration_minutes' => 60,
            'party_size' => 2,
            'reservation_units' => 2,
            'total_price' => 100,
            'unit_price' => 100,
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);

        $slots = app(SlotGenerationService::class)->slotsFor($service, $date, reservationUnits: 2);

        $this->assertCount(1, $slots);
        $this->assertSame(1, $slots[0]['available'], '3 capacity minus 2 booked units = 1 left');
        $this->assertFalse($slots[0]['can_accommodate'], '1 remaining is not enough for 2 requested units');
    }

    public function test_falls_back_to_weekly_slots_when_no_resources(): void
    {
        [$business, $service] = $this->simpleService();
        $date = now()->addDays(2);
        $service->timeSlots()->create([
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $slots = app(SlotGenerationService::class)->slotsFor($service, $date);

        $this->assertCount(1, $slots);
        $this->assertSame('09:00', $slots[0]['start_time']);
        $this->assertArrayHasKey('time_slot_id', $slots[0]);
    }

    public function test_booking_flow_still_works_when_service_has_resources(): void
    {
        [$business, $service, $resource] = $this->resourceService();
        $date = now()->addDays(2);
        AvailabilityRule::create([
            'resource_id' => $resource->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'slot_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'capacity' => 5,
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/businesses/{$business->slug}/bookings", [
            'service_id' => $service->id,
            'customer_name' => 'Slot Customer',
            'customer_phone' => '9000000002',
            'customer_email' => 'slot@example.test',
            'booking_date' => $date->toDateString(),
            'start_time' => '10:00',
            'party_size' => 2,
            'client_reference' => 'resource-slot-1',
        ]);

        $response->assertCreated()->assertJsonPath('booking.booking_type', 'time_slot');
        $this->assertSame('pending', Booking::firstOrFail()->status);
    }

    private function resourceService(): array
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
            'name' => "Resource Biz {$suffix}",
            'slug' => "resource-biz-{$suffix}",
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
                'turf' => true,
            ],
        ]);
        $service = Service::create([
            'business_id' => $business->id,
            'name' => "Turf {$suffix}",
            'price' => 100,
            'duration' => 60,
            'capacity' => 5,
            'booking_mode' => 'slot',
            'inventory_units' => 1,
            'advance_booking_days' => 30,
            'cancellation_hours' => 2,
            'has_fixed_slots' => true,
            'is_active' => true,
        ]);
        $resource = BookableResource::create([
            'business_id' => $business->id,
            'service_id' => $service->id,
            'resource_type' => 'slot',
            'name' => 'Court A',
            'capacity' => 5,
            'is_active' => true,
        ]);

        return [$business, $service, $resource];
    }

    private function simpleService(): array
    {
        $business = $this->resourceService()[0];
        $service = Service::create([
            'business_id' => $business->id,
            'name' => 'Simple Slot '.str()->lower(str()->random(8)),
            'price' => 80,
            'duration' => 60,
            'capacity' => 4,
            'booking_mode' => 'slot',
            'inventory_units' => 1,
            'advance_booking_days' => 30,
            'cancellation_hours' => 2,
            'has_fixed_slots' => true,
            'is_active' => true,
        ]);

        return [$business, $service];
    }
}
