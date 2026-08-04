<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\ShopSection;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\User;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomySeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_category_manager_shows_only_canonical_classifications(): void
    {
        $this->seed(WorldSeeder::class);

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.category-tree'))
            ->assertOk()
            ->assertSee('Directory Category Manager')
            ->assertSee('Food & Restaurants')
            ->assertSee('Street Food')
            ->assertDontSee('>Electronics</span>')
            ->assertDontSee('>Shopping</h3>')
            ->assertDontSee('>Booking</h3>');
    }

    public function test_shop_sections_crud_is_independent_of_directory_taxonomy(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        FeatureFlag::where('key', 'world.shop')->firstOrFail()->update(['is_enabled' => true]);
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post(route('admin.shop-sections.store'), [
                'name' => 'Grocery Express',
                'description' => 'Fresh produce',
            ])
            ->assertRedirect(route('admin.shop-sections'));

        $this->assertDatabaseHas('shop_sections', ['name' => 'Grocery Express']);

        $section = ShopSection::where('slug', 'grocery-express')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.shop-sections.toggle', $section->id))
            ->assertRedirect();
        $this->assertFalse($section->fresh()->is_active);

        $this->actingAs($admin)
            ->delete(route('admin.shop-sections.destroy', $section->id))
            ->assertRedirect();
        $this->assertDatabaseMissing('shop_sections', ['id' => $section->id]);
    }

    public function test_product_categories_are_managed_per_business_type_with_subcategories(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        $this->seed(WorldSeeder::class);
        FeatureFlag::where('key', 'world.shop')->firstOrFail()->update(['is_enabled' => true]);
        LaunchControlService::clearCache();

        $groceryType = Category::where('slug', 'grocery')->firstOrFail();
        $section = ShopSection::where('slug', 'grocery')->firstOrFail();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post(route('admin.product-categories.store'), [
                'business_type_id' => $groceryType->id,
                'shop_section_id' => $section->id,
                'name' => 'Fruits',
            ])
            ->assertRedirect(route('admin.product-categories', ['business_type_id' => $groceryType->id]));

        $this->assertDatabaseHas('product_categories', [
            'business_type_id' => $groceryType->id,
            'shop_section_id' => $section->id,
            'name' => 'Fruits',
            'parent_id' => null,
        ]);

        $fruits = \App\Models\ProductCategory::where('business_type_id', $groceryType->id)
            ->where('name', 'Fruits')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.product-categories.store'), [
                'business_type_id' => $groceryType->id,
                'parent_id' => $fruits->id,
                'name' => 'Apples',
            ])
            ->assertRedirect(route('admin.product-categories', ['business_type_id' => $groceryType->id]));

        $this->assertDatabaseHas('product_categories', [
            'business_type_id' => $groceryType->id,
            'parent_id' => $fruits->id,
            'name' => 'Apples',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.product-categories', ['business_type_id' => $groceryType->id]))
            ->assertOk()
            ->assertSee('Fruits')
            ->assertSee('Apples')
            ->assertDontSee('business_id');
    }

    public function test_vehicle_types_are_globally_managed_and_seeded_with_defaults(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        FeatureFlag::where('key', 'world.ride')->firstOrFail()->update(['is_enabled' => true]);
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.vehicle-types'))
            ->assertOk()
            ->assertSee('Car')
            ->assertSee('Truck')
            ->assertSee('Bus');

        $this->actingAs($admin)
            ->post(route('admin.vehicle-types.store'), [
                'name' => 'Tempo Traveller',
                'slug' => 'tempo-traveller',
                'description' => '12-18 seater van',
            ])
            ->assertRedirect(route('admin.vehicle-types'));

        $this->assertDatabaseHas('vehicle_types', ['name' => 'Tempo Traveller']);

        $type = VehicleType::where('slug', 'tempo-traveller')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.vehicle-types.toggle', $type->id))
            ->assertRedirect();
        $this->assertFalse($type->fresh()->is_active);
    }

    public function test_vehicle_type_used_by_vehicles_cannot_be_deleted(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        FeatureFlag::where('key', 'world.ride')->firstOrFail()->update(['is_enabled' => true]);
        LaunchControlService::clearCache();

        $category = Category::where('slug', 'transport')->firstOrFail();
        $business = Business::create([
            'name' => 'Test Transport',
            'slug' => 'test-transport',
            'category_id' => $category->id,
            'address' => 'Test street',
        ]);

        $truck = VehicleType::where('slug', 'truck')->firstOrFail();
        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Heavy Truck',
            'type' => 'truck',
            'service_mode' => 'goods',
            'seats' => 2,
            'capacity_unit' => 'tons',
            'base_fare' => 500,
            'fare_per_km' => 25,
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->delete(route('admin.vehicle-types.destroy', $truck->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('vehicle_types', ['id' => $truck->id]);

        $freeType = VehicleType::where('slug', 'car')->firstOrFail();
        $this->actingAs($admin)
            ->delete(route('admin.vehicle-types.destroy', $freeType->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('vehicle_types', ['id' => $freeType->id]);
    }
}
