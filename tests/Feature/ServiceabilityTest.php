<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Pincode;
use App\Services\DeliveryEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_discovers_business_without_a_pincode(): void
    {
        $day = strtolower(now()->format('l'));
        $business = $this->business([
            'working_hours' => [$day => ['open' => 'not-a-time', 'close' => 'also-invalid']],
        ]);

        $this->get('/explore')
            ->assertOk()
            ->assertSee($business->name);

        $this->get('/businesses')
            ->assertOk()
            ->assertSee($business->name);

        $this->get("/business/{$business->slug}")
            ->assertOk()
            ->assertSee($business->name);
    }

    public function test_home_search_progressively_enhances_to_the_api_and_explore_results(): void
    {
        $business = $this->business();

        $this->get('/')
            ->assertOk()
            ->assertSee('action="'.route('explore').'"', false)
            ->assertSee('name="q"', false)
            ->assertSee('/api/instant-search', false);

        $this->getJson('/api/instant-search?q=Serviceability')
            ->assertOk()
            ->assertJsonPath('results.0.id', $business->id)
            ->assertJsonPath('results.0.name', $business->name);
    }

    public function test_transaction_scope_uses_serviceable_district_when_legacy_business_has_no_pincode(): void
    {
        Pincode::create([
            'pincode' => '795128',
            'locality' => 'Lamka',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'serviceable' => true,
        ]);
        $business = $this->business();

        $this->assertTrue(Business::inServiceableArea()->whereKey($business->id)->exists());
    }

    public function test_zone_coverage_respects_minimum_order(): void
    {
        $pincode = $this->serviceablePincode();
        $business = $this->business(['pincode' => $pincode->pincode]);
        $area = Area::where('slug', 'lamka')->firstOrFail();
        DeliveryZone::create([
            'business_id' => $business->id,
            'area_id' => $area->id,
            'pincodes' => [$pincode->pincode],
            'min_order_amount' => 500,
            'delivery_fee' => 40,
            'estimated_minutes' => 45,
            'is_active' => true,
        ]);

        $result = app(DeliveryEligibilityService::class)->check(
            $business,
            customerPincode: $pincode->pincode,
            subtotal: 300,
        );

        $this->assertTrue($result['available']);
        $this->assertFalse($result['deliverable']);
        $this->assertFalse($result['above_minimum']);
        $this->assertSame('pincode_zone', $result['matched_by']);
        $this->assertSame(40.0, $result['delivery_fee']);
    }

    public function test_radius_coverage_works_without_a_delivery_zone(): void
    {
        $pincode = $this->serviceablePincode();
        $business = $this->business([
            'pincode' => $pincode->pincode,
            'latitude' => 24.335,
            'longitude' => 93.705,
            'delivery_radius_km' => 5,
        ]);

        $result = app(DeliveryEligibilityService::class)->check(
            $business,
            latitude: 24.336,
            longitude: 93.706,
            subtotal: 100,
        );

        $this->assertTrue($result['available']);
        $this->assertTrue($result['deliverable']);
        $this->assertSame('radius', $result['matched_by']);
        $this->assertLessThan(1, $result['distance_km']);
    }

    public function test_non_serviceable_location_stays_discoverable_but_cannot_transact(): void
    {
        $pincode = Pincode::create([
            'pincode' => '110001',
            'locality' => 'New Delhi',
            'district' => 'New Delhi',
            'state' => 'Delhi',
            'serviceable' => false,
        ]);
        $business = $this->business([
            'pincode' => $pincode->pincode,
            'district' => $pincode->district,
            'state' => $pincode->state,
        ]);

        $this->assertTrue(Business::active()->whereKey($business->id)->exists());
        $this->assertFalse(Business::inServiceableArea()->whereKey($business->id)->exists());
        $this->assertFalse(app(DeliveryEligibilityService::class)->check($business, $pincode->pincode)['deliverable']);
    }

    private function serviceablePincode(): Pincode
    {
        return Pincode::create([
            'pincode' => '795128',
            'locality' => 'Lamka',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'latitude' => 24.335,
            'longitude' => 93.705,
            'serviceable' => true,
        ]);
    }

    private function business(array $attributes = []): Business
    {
        $category = Category::where('slug', 'professional-services')->firstOrFail();

        return Business::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Serviceability Test Business',
            'slug' => 'serviceability-test-'.uniqid(),
            'address' => 'Test Road',
            'district' => 'Churachandpur',
            'source' => 'vendor',
            'enabled_modules' => [],
        ], $attributes));
    }
}
