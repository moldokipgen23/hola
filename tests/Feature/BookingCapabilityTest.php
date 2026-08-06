<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_only_business_gets_call_cta(): void
    {
        $business = $this->makeBusiness(enabledModules: []);

        $this->getJson('/api/businesses/'.$business->slug)
            ->assertOk()
            ->assertJsonPath('business.booking.can_book_online', false)
            ->assertJsonPath('business.booking.book_cta', 'call_or_whatsapp');
    }

    public function test_unverified_bookings_business_cannot_book_online(): void
    {
        $owner = User::factory()->create();
        $business = $this->makeBusiness(
            enabledModules: ['bookings' => true],
            owner: $owner,
            verification: 'pending',
        );

        $this->getJson('/api/businesses/'.$business->slug)
            ->assertOk()
            ->assertJsonPath('business.booking.can_book_online', false)
            ->assertJsonPath('business.booking.book_cta', 'call_or_whatsapp');
    }

    public function test_verified_claimed_business_with_ready_services_books_in_app(): void
    {
        $owner = User::factory()->create();
        $business = $this->makeBusiness(
            enabledModules: ['bookings' => true],
            owner: $owner,
            verification: 'verified',
        );
        Service::create([
            'business_id' => $business->id,
            'name' => 'Haircut',
            'price' => 100,
            'duration' => 45,
            'capacity' => 1,
            'advance_booking_days' => 30,
            'is_active' => true,
        ]);

        $this->getJson('/api/businesses/'.$business->slug)
            ->assertOk()
            ->assertJsonPath('business.booking.can_book_online', true)
            ->assertJsonPath('business.booking.book_cta', 'in_app')
            ->assertJsonPath('business.booking.ready_experiences.0', 'appointment');
    }

    public function test_unclaimed_business_gets_call_cta_even_if_verified(): void
    {
        $business = $this->makeBusiness(
            enabledModules: ['bookings' => true],
            owner: null,
            verification: 'verified',
        );
        Service::create([
            'business_id' => $business->id,
            'name' => 'Service',
            'price' => 50,
            'duration' => 30,
            'capacity' => 1,
            'advance_booking_days' => 30,
            'is_active' => true,
        ]);

        $this->getJson('/api/businesses/'.$business->slug)
            ->assertOk()
            ->assertJsonPath('business.booking.can_book_online', false)
            ->assertJsonPath('business.booking.book_cta', 'call_or_whatsapp');
    }

    public function test_search_results_include_booking_capability(): void
    {
        $owner = User::factory()->create();
        $business = $this->makeBusiness(
            enabledModules: ['bookings' => true],
            owner: $owner,
            verification: 'verified',
        );
        Service::create([
            'business_id' => $business->id,
            'name' => 'Electrician visit',
            'price' => 300,
            'duration' => 60,
            'capacity' => 1,
            'advance_booking_days' => 30,
            'is_active' => true,
        ]);

        $this->getJson('/api/search/universal?q='.substr($business->name, 0, 12))
            ->assertOk()
            ->assertJsonPath('sections.0.items.0.booking.can_book_online', true)
            ->assertJsonPath('sections.0.items.0.booking.book_cta', 'in_app');
    }

    private function makeBusiness(
        array $enabledModules = [],
        ?User $owner = null,
        string $verification = 'pending',
    ): Business {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'Test Locality',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'serviceable' => true,
        ]);
        $category = Category::where('slug', 'beauty-wellness')->firstOrFail();
        $suffix = str()->lower(str()->random(8));

        return Business::create([
            'category_id' => $category->id,
            'name' => 'Capability Biz '.$suffix,
            'slug' => 'capability-biz-'.$suffix,
            'address' => 'Test address',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'pincode' => '795128',
            'phone' => '9876543210',
            'whatsapp' => '9876543210',
            'created_by' => $owner?->id,
            'verification_status' => $verification,
            'is_active' => true,
            'enabled_modules' => array_merge([
                'catalog' => false,
                'orders' => false,
                'bookings' => false,
                'inventory' => false,
                'transport' => false,
                'turf' => false,
            ], $enabledModules),
        ]);
    }
}
