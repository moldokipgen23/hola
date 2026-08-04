<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\ShopSection;
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

    public function test_product_categories_are_managed_per_business_under_a_shop_section(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        FeatureFlag::where('key', 'world.shop')->firstOrFail()->update(['is_enabled' => true]);
        LaunchControlService::clearCache();

        $category = Category::where('slug', 'food-restaurants')->firstOrFail();
        $business = Business::create([
            'name' => 'Test Cafe',
            'slug' => 'test-cafe',
            'category_id' => $category->id,
            'address' => 'Test street',
        ]);
        $section = ShopSection::where('slug', 'grocery')->firstOrFail();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post(route('admin.product-categories.store'), [
                'business_id' => $business->id,
                'shop_section_id' => $section->id,
                'name' => 'Fruits',
            ])
            ->assertRedirect(route('admin.product-categories', ['business_id' => $business->id]));

        $this->assertDatabaseHas('product_categories', [
            'business_id' => $business->id,
            'shop_section_id' => $section->id,
            'name' => 'Fruits',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.product-categories', ['business_id' => $business->id]))
            ->assertOk()
            ->assertSee('Fruits')
            ->assertSee('Grocery');
    }
}
