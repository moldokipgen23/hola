<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorPortalOperationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_hotel_inventory_from_the_web_portal(): void
    {
        [$owner, $business] = $this->business('hotels-lodges', ['bookings' => true]);

        $this->actingAs($owner)->post(route('vendor.services.store', $business->id), [
            'name' => 'Deluxe Room', 'description' => 'Two guest room',
            'booking_mode' => 'stay', 'price' => 1500, 'price_unit' => 'night',
            'inventory_units' => 5, 'unit_label' => 'room',
            'check_in_time' => '14:00', 'check_out_time' => '11:00',
            'min_stay_nights' => 1, 'max_stay_nights' => 14,
            'advance_booking_days' => 180, 'cancellation_hours' => 24,
            'is_active' => 1,
        ])->assertRedirect(route('vendor.services', $business->id));

        $this->assertDatabaseHas('services', [
            'business_id' => $business->id, 'name' => 'Deluxe Room',
            'booking_mode' => 'stay', 'inventory_units' => 5,
            'price_unit' => 'night', 'has_fixed_slots' => false,
        ]);
    }

    public function test_vendor_can_create_turf_slots_and_manage_transport_options(): void
    {
        [$owner, $business] = $this->business('sports-fitness', ['bookings' => true, 'turf' => true, 'transport' => true]);
        $service = Service::create([
            'business_id' => $business->id, 'name' => 'Football Turf',
            'booking_mode' => 'slot', 'price' => 1000, 'price_unit' => 'hour',
            'duration' => 60, 'capacity' => 20, 'inventory_units' => 1,
            'has_fixed_slots' => true, 'is_active' => true,
        ]);

        $this->actingAs($owner)->post(route('vendor.services.slots.store', [$business->id, $service->id]), [
            'day_of_week' => 6, 'start_time' => '18:00', 'end_time' => '19:00',
            'capacity' => 1, 'price_override' => 1200,
        ])->assertRedirect();
        $this->assertDatabaseHas('time_slots', ['service_id' => $service->id, 'day_of_week' => 6, 'capacity' => 1]);

        $this->post(route('vendor.vehicles.store', $business->id), [
            'name' => 'Local Taxi 1', 'type' => 'car', 'service_mode' => 'taxi',
            'seats' => 4, 'capacity_unit' => 'seats', 'base_fare' => 100,
            'fare_per_km' => 15, 'min_km' => 2,
        ])->assertRedirect();
        $this->assertDatabaseHas('vehicles', ['business_id' => $business->id, 'name' => 'Local Taxi 1', 'service_mode' => 'taxi']);

        $this->get(route('vendor.services.slots', [$business->id, $service->id]))->assertOk();
        $this->get(route('vendor.vehicles', $business->id))->assertOk();
        $this->get(route('vendor.trips', $business->id))->assertOk();
    }

    public function test_vendor_cannot_access_another_owners_operations(): void
    {
        [, $business] = $this->business('transport', ['transport' => true]);
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('vendor.vehicles', $business->id))->assertNotFound();
        $this->get(route('vendor.trips', $business->id))->assertNotFound();
    }

    private function business(string $categorySlug, array $modules): array
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'Test Locality', 'district' => 'Churachandpur',
            'state' => 'Manipur', 'serviceable' => true,
        ]);
        $owner = User::factory()->create();
        $category = Category::where('slug', $categorySlug)->firstOrFail();
        $suffix = str()->lower(str()->random(8));
        $defaults = ['catalog' => false, 'orders' => false, 'bookings' => false, 'inventory' => false, 'transport' => false, 'turf' => false];
        $business = Business::create([
            'created_by' => $owner->id, 'category_id' => $category->id,
            'name' => "Vendor Business {$suffix}", 'slug' => "vendor-business-{$suffix}",
            'address' => 'Test address', 'district' => 'Churachandpur', 'state' => 'Manipur',
            'pincode' => '795128', 'phone' => '9876543210', 'is_active' => true,
            'enabled_modules' => array_merge($defaults, $modules),
        ]);

        return [$owner, $business];
    }
}
