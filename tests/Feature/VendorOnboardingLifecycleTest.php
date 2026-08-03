<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CapabilityTemplate;
use App\Models\Category;
use App\Models\ClaimRequest;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOnboardingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_approval_auto_verifies_directory_only_business(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $claimant = User::factory()->create(['role' => 'customer']);
        $business = $this->business(['enabled_modules' => [], 'enabled_experiences' => ['directory']]);
        $claim = ClaimRequest::create([
            'user_id' => $claimant->id,
            'business_id' => $business->id,
            'status' => 'pending',
            'message' => 'This is my shop',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.claims.approve', $claim->id))
            ->assertRedirect();

        $business->refresh();
        $this->assertSame('claimed', $business->claim_status);
        $this->assertSame('verified', $business->verification_status);
        $this->assertTrue($business->is_active);
        $this->assertSame($claimant->id, $business->created_by);
        $this->assertSame('owner', $claimant->refresh()->role);
    }

    public function test_claim_approval_keeps_transactional_business_pending_until_admin_verify(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $claimant = User::factory()->create(['role' => 'customer']);
        $business = $this->business([
            'enabled_modules' => ['catalog' => true, 'orders' => true],
            'enabled_experiences' => ['retail', 'directory'],
        ]);
        $claim = ClaimRequest::create([
            'user_id' => $claimant->id,
            'business_id' => $business->id,
            'status' => 'pending',
            'message' => 'Mine',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.claims.approve', $claim->id))
            ->assertRedirect();

        $business->refresh();
        $this->assertSame('claimed', $business->claim_status);
        $this->assertSame('pending', $business->verification_status);

        $this->actingAs($admin)
            ->post(route('admin.businesses.verify', $business->id))
            ->assertRedirect();

        $this->assertSame('verified', $business->refresh()->verification_status);
        $this->assertTrue($business->is_active);
    }

    public function test_vendor_self_create_requires_admin_verification_to_go_live(): void
    {
        $vendor = User::factory()->create(['role' => 'owner']);
        $category = Category::firstOrFail();

        $this->actingAs($vendor)
            ->post(route('vendor.businesses.store'), [
                'name' => 'My New Shop',
                'category_id' => $category->id,
                'address' => 'Main Road',
                'phone' => '9876543210',
                'description' => 'Fresh from the owner',
            ])
            ->assertRedirect();

        $business = Business::where('name', 'My New Shop')->firstOrFail();
        $this->assertSame($vendor->id, $business->created_by);
        $this->assertSame('claimed', $business->claim_status);
        $this->assertSame('pending', $business->verification_status);
        $this->assertSame('vendor', $business->source);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->post(route('admin.businesses.verify', $business->id))
            ->assertRedirect();

        $this->assertSame('verified', $business->refresh()->verification_status);
    }

    public function test_choosing_take_bookings_enables_bookings_module(): void
    {
        $vendor = User::factory()->create(['role' => 'owner']);
        $business = $this->business(['created_by' => $vendor->id, 'enabled_modules' => []]);

        $this->actingAs($vendor)
            ->post(route('vendor.businesses.setup.post', $business->id), ['offer' => 'book'])
            ->assertRedirect(route('vendor.dashboard'));

        $this->assertTrue($business->refresh()->hasModule('bookings'));
        $this->assertFalse($business->hasModule('catalog'));
    }

    public function test_choosing_sell_products_applies_retail_template(): void
    {
        $vendor = User::factory()->create(['role' => 'owner']);
        $business = $this->business(['created_by' => $vendor->id, 'enabled_modules' => []]);

        $this->actingAs($vendor)
            ->post(route('vendor.businesses.setup.post', $business->id), ['offer' => 'sell'])
            ->assertRedirect(route('vendor.dashboard'));

        $this->assertTrue($business->refresh()->hasModule('catalog'));
        $this->assertTrue($business->hasModule('orders'));
        $this->assertSame('retail', $business->primary_experience);
    }

    public function test_retired_wizard_routes_redirect_to_offer_screen(): void
    {
        $vendor = User::factory()->create(['role' => 'owner']);
        $business = $this->business(['created_by' => $vendor->id]);

        $this->actingAs($vendor)
            ->get(route('vendor.onboarding.step', ['id' => $business->id, 'step' => 3]))
            ->assertRedirect(route('vendor.businesses.setup', $business->id));

        $this->actingAs($vendor)
            ->post(route('vendor.onboarding.store', ['id' => $business->id, 'step' => 3]))
            ->assertRedirect(route('vendor.businesses.setup', $business->id));
    }

    public function test_dashboard_shows_readiness_percentage_and_next_step(): void
    {
        $vendor = User::factory()->create(['role' => 'owner']);
        $business = $this->business(['created_by' => $vendor->id]);

        $this->actingAs($vendor)
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSee('Readiness checklist')
            ->assertSee('Add products or services');

        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Rice Bag',
            'slug' => 'rice-bag-'.str()->lower(str()->random(6)),
            'price' => 100,
            'is_active' => true,
        ]);
        $this->assertNotNull($product->id);

        $this->actingAs($vendor)
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSee('Upload photos');
    }

    public function test_vendor_analytics_page_loads(): void
    {
        $vendor = User::factory()->create(['role' => 'owner']);
        $this->business(['created_by' => $vendor->id]);

        $this->actingAs($vendor)
            ->get(route('vendor.analytics'))
            ->assertOk();
    }

    public function test_turf_sidebar_link_gates_on_bookings_and_turf_module(): void
    {
        $vendor = User::factory()->create(['role' => 'owner']);
        $catalogOnly = $this->business([
            'created_by' => $vendor->id,
            'enabled_modules' => ['catalog' => true],
        ]);

        $this->actingAs($vendor)
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertDontSee('Turf / Sports');

        $turf = $this->business([
            'created_by' => $vendor->id,
            'name' => 'Turf Arena',
            'slug' => 'turf-arena-'.str()->lower(str()->random(6)),
            'enabled_modules' => ['bookings' => true, 'turf' => true],
        ]);

        $this->assertNotNull($catalogOnly->id);
        $this->assertNotNull($turf->id);

        $this->actingAs($vendor)
            ->get(route('vendor.businesses.turf', $turf->id))
            ->assertOk()
            ->assertSee('Turf / Sports');
    }

    private function business(array $overrides = []): Business
    {
        $category = Category::firstOrFail();

        return Business::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Lifecycle Business '.str()->random(8),
            'slug' => 'lifecycle-business-'.str()->lower(str()->random(8)),
            'address' => 'Test address',
            'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => [],
        ], $overrides));
    }
}
