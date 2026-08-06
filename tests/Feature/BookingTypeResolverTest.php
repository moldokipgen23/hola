<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\User;
use App\Services\BookingTypeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTypeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_salon_category_maps_to_appointment(): void
    {
        $business = $this->businessIn('Salons', 'salons');
        $this->assertSame('appointment', app(BookingTypeResolver::class)->experienceForCategory($business->category));
        $setup = app(BookingTypeResolver::class)->setupFor($business);
        $this->assertSame(['appointment', 'directory'], $setup['experiences']);
        $this->assertTrue($setup['modules']['bookings']);
    }

    public function test_electrician_maps_to_appointment(): void
    {
        $business = $this->businessIn('Electricians', 'electricians');
        $this->assertSame('appointment', app(BookingTypeResolver::class)->experienceForCategory($business->category));
    }

    public function test_hotel_category_maps_to_stay(): void
    {
        $business = $this->businessIn('Hotels', 'hotels');
        $this->assertSame('stay', app(BookingTypeResolver::class)->experienceForCategory($business->category));
        $setup = app(BookingTypeResolver::class)->setupFor($business);
        $this->assertSame(['stay', 'directory'], $setup['experiences']);
    }

    public function test_turf_category_maps_to_turf_with_turf_module(): void
    {
        $business = $this->businessIn('Football Turf', 'football-turf');
        $this->assertSame('turf', app(BookingTypeResolver::class)->experienceForCategory($business->category));
        $setup = app(BookingTypeResolver::class)->setupFor($business);
        $this->assertSame(['turf', 'directory'], $setup['experiences']);
        $this->assertTrue($setup['modules']['turf']);
    }

    public function test_uncategorised_falls_back_to_appointment(): void
    {
        $this->assertSame('appointment', app(BookingTypeResolver::class)->experienceForCategory(null));
    }

    public function test_setup_post_enables_category_matched_booking(): void
    {
        $owner = User::factory()->create();
        $business = $this->businessIn('Hotels', 'hotels', owner: $owner, verified: true);

        $this->actingAs($owner)
            ->post('/vendor/businesses/'.$business->id.'/setup', ['offer' => 'book'])
            ->assertRedirect();

        $fresh = $business->fresh();
        $this->assertTrue($fresh->hasModule('bookings'));
        $this->assertContains('stay', $fresh->enabled_experiences);
        $this->assertSame('stay', $fresh->primary_experience);
    }

    private function businessIn(string $name, string $slug, ?User $owner = null, bool $verified = false): Business
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'Test Locality',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'serviceable' => true,
        ]);
        $category = Category::updateOrCreate(['slug' => $slug], [
            'name' => $name,
            'module_type' => 'booking',
            'is_active' => true,
        ]);
        $suffix = str()->lower(str()->random(8));

        return Business::create([
            'category_id' => $category->id,
            'name' => "{$name} {$suffix}",
            'slug' => "type-test-{$suffix}",
            'address' => 'Test address',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'pincode' => '795128',
            'phone' => '9876543210',
            'created_by' => $owner?->id,
            'verification_status' => $verified ? 'verified' : 'pending',
            'is_active' => true,
        ]);
    }

    public function test_generic_professional_services_fall_back_to_appointment(): void
    {
        $owner = User::factory()->create();
        $category = Category::updateOrCreate(['slug' => 'legal-services'], [
            'name' => 'Legal Services',
            'module_type' => 'directory',
            'is_active' => true,
        ]);
        $business = Business::create([
            'category_id' => $category->id,
            'name' => 'Legal Consult '.str()->lower(str()->random(8)),
            'slug' => 'legal-'.str()->lower(str()->random(8)),
            'address' => 'Test address',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'pincode' => '795128',
            'phone' => '9876543210',
            'created_by' => $owner->id,
            'verification_status' => 'verified',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->post('/vendor/businesses/'.$business->id.'/setup', ['offer' => 'book'])
            ->assertRedirect();

        $fresh = $business->fresh();
        $this->assertTrue($fresh->hasModule('bookings'));
        $this->assertContains('appointment', $fresh->enabled_experiences);
        $this->assertSame('appointment', $fresh->primary_experience);
    }
}
