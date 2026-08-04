<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use App\Services\BusinessModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessModuleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_dependencies_are_normalized_without_losing_combinations(): void
    {
        $modules = app(BusinessModuleService::class)->normalize([
            'orders' => true,
            'bookings' => true,
            'turf' => true,
            'transport' => true,
        ]);

        $this->assertTrue($modules['catalog']);
        $this->assertTrue($modules['orders']);
        $this->assertTrue($modules['bookings']);
        $this->assertTrue($modules['turf']);
        $this->assertTrue($modules['transport']);
        $this->assertFalse($modules['inventory']);
    }

    public function test_category_change_does_not_overwrite_explicit_vendor_modules(): void
    {
        $business = $this->business([
            'enabled_modules' => ['orders' => true, 'bookings' => true],
        ]);
        $newCategory = Category::where('slug', 'government-public-services')->firstOrFail();

        $business->update(['category_id' => $newCategory->id]);

        $this->assertTrue($business->fresh()->hasModule('orders'));
        $this->assertTrue($business->fresh()->hasModule('bookings'));
    }

    public function test_explicit_empty_subcategory_recommendation_remains_explore_only(): void
    {
        $category = Category::where('slug', 'healthcare')->firstOrFail();
        $hospital = Subcategory::where('category_id', $category->id)->where('slug', 'hospitals')->firstOrFail();
        $business = $this->business([
            'category_id' => $category->id,
            'subcategory_id' => $hospital->id,
        ]);

        $this->assertSame([], app(BusinessModuleService::class)->recommendedFor($business));
        $this->assertFalse($business->hasModule('bookings'));
        $this->assertFalse($business->hasModule('orders'));
    }

    public function test_owner_can_enable_booking_before_setup_and_receives_readiness_guidance(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $business = $this->business(['created_by' => $owner->id, 'enabled_modules' => []]);

        $response = $this->actingAs($owner)->putJson("/api/owner/businesses/{$business->id}/modules", [
            'bookings' => true,
            'orders' => true,
            'module_config' => ['confirmation_mode' => 'manual'],
        ]);

        $response->assertOk()
            ->assertJsonPath('enabled_modules.bookings', true)
            ->assertJsonPath('enabled_modules.orders', true)
            ->assertJsonPath('enabled_modules.catalog', true)
            ->assertJsonPath('readiness.bookings.ready', false);

        $business->refresh();
        $this->assertArrayNotHasKey('module_config', $business->enabled_modules);
        $this->assertSame(['confirmation_mode' => 'manual'], $business->module_config);
        $this->assertTrue($business->is_bookable);
    }

    public function test_vendor_feature_screen_saves_multiple_capabilities(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $business = $this->business(['created_by' => $owner->id, 'enabled_modules' => []]);

        $this->actingAs($owner)
            ->get(route('vendor.businesses.modules', $business))
            ->assertOk()
            ->assertSee('Every business is always visible in Explore');

        $this->actingAs($owner)
            ->put(route('vendor.businesses.modules.update', $business), [
                'modules' => ['orders', 'bookings', 'transport'],
            ])
            ->assertRedirect(route('vendor.businesses.modules', $business));

        $business->refresh();
        $this->assertTrue($business->hasModule('catalog'));
        $this->assertTrue($business->hasModule('orders'));
        $this->assertTrue($business->hasModule('bookings'));
        $this->assertTrue($business->hasModule('transport'));
    }

    public function test_catalog_can_be_managed_without_enabling_orders(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $business = $this->business([
            'created_by' => $owner->id,
            'enabled_modules' => ['catalog' => true],
        ]);

        $this->actingAs($owner)
            ->get(route('vendor.products', $business))
            ->assertOk();

        $this->assertTrue($business->hasModule('catalog'));
        $this->assertFalse($business->hasModule('orders'));
    }

    public function test_explore_filters_catalog_only_business_as_shopping_and_empty_business_as_directory(): void
    {
        $shopping = $this->business(['enabled_modules' => ['catalog' => true]]);
        $directory = $this->business([
            'name' => 'Directory Business',
            'slug' => 'directory-business-'.uniqid(),
            'enabled_modules' => [],
        ]);

        $this->getJson('/api/businesses?module=ordering')
            ->assertOk()
            ->assertJsonPath('businesses.total', 1)
            ->assertJsonPath('businesses.data.0.id', $shopping->id);

        $this->getJson('/api/businesses?module=directory')
            ->assertOk()
            ->assertJsonPath('businesses.total', 1)
            ->assertJsonPath('businesses.data.0.id', $directory->id);
    }

    public function test_legacy_null_capabilities_still_follow_category_until_backfilled(): void
    {
        $business = $this->business(['enabled_modules' => ['catalog' => true]]);
        DB::table('businesses')->where('id', $business->id)->update(['enabled_modules' => null]);

        $this->getJson('/api/businesses?module=ordering')
            ->assertOk()
            ->assertJsonPath('businesses.total', 1)
            ->assertJsonPath('businesses.data.0.id', $business->id);
    }

    private function business(array $attributes = []): Business
    {
        $category = Category::where('slug', 'shopping-retail')->firstOrFail();

        return Business::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Module Test Business',
            'slug' => 'module-test-business-'.uniqid(),
            'address' => 'Test Road',
            'source' => 'vendor',
            'verification_status' => 'verified',
        ], $attributes));
    }
}
