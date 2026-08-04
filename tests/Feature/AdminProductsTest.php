<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_page_filters_by_shopping_business_type_tabs(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        $this->seed(WorldSeeder::class);
        FeatureFlag::where('key', 'world.shop')->firstOrFail()->update(['is_enabled' => true]);
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $groceryType = Category::where('slug', 'grocery')->firstOrFail();
        $medicineType = Category::where('slug', 'medicine')->firstOrFail();

        $groceryBiz = Business::create([
            'name' => 'Quick Mart',
            'slug' => 'quick-mart-'.uniqid(),
            'category_id' => $groceryType->id,
            'address' => 'Test street',
            'enabled_modules' => ['catalog' => true, 'orders' => true],
        ]);
        $medicineBiz = Business::create([
            'name' => 'City Pharmacy',
            'slug' => 'city-pharmacy-'.uniqid(),
            'category_id' => $medicineType->id,
            'address' => 'Test street',
            'enabled_modules' => ['catalog' => true, 'orders' => true],
        ]);

        $groceries = ProductCategory::create(['business_id' => $groceryBiz->id, 'name' => 'Staples', 'slug' => 'staples-'.uniqid(), 'is_active' => true]);
        $medicines = ProductCategory::create(['business_id' => $medicineBiz->id, 'name' => 'OTC', 'slug' => 'otc-'.uniqid(), 'is_active' => true]);

        Product::create(['business_id' => $groceryBiz->id, 'product_category_id' => $groceries->id, 'name' => 'Rice 5kg', 'slug' => 'rice-5kg-'.uniqid(), 'price' => 350]);
        Product::create(['business_id' => $medicineBiz->id, 'product_category_id' => $medicines->id, 'name' => 'Paracetamol', 'slug' => 'paracetamol-'.uniqid(), 'price' => 20]);

        $this->actingAs($admin)
            ->get(route('admin.products', ['business_type_id' => $groceryType->id]))
            ->assertOk()
            ->assertSee('Rice 5kg')
            ->assertSee('Grocery')
            ->assertDontSee('Paracetamol');

        $this->actingAs($admin)
            ->get(route('admin.products', ['business_type_id' => $medicineType->id]))
            ->assertOk()
            ->assertSee('Paracetamol')
            ->assertDontSee('Rice 5kg');

        $this->actingAs($admin)
            ->get(route('admin.products'))
            ->assertOk()
            ->assertSee('Rice 5kg')
            ->assertSee('Paracetamol')
            ->assertSee('Grocery')
            ->assertSee('Medicine');
    }
}
