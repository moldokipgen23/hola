<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $email): User
    {
        return User::factory()->create(['email' => $email, 'role' => 'owner']);
    }

    private function category(string $name, string $moduleType): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'module_type' => $moduleType,
            'is_canonical' => true,
            'is_active' => true,
        ]);
    }

    private function verifiedBusiness(User $owner, Category $category, array $modules): Business
    {
        return Business::create([
            'name' => 'Demo Shop '.uniqid(),
            'slug' => 'demo-shop-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Test street',
            'created_by' => $owner->id,
            'claim_status' => 'claimed',
            'verification_status' => 'verified',
            'enabled_modules' => $modules,
        ]);
    }

    public function test_demo_catalog_seeder_creates_categories_and_products_for_shopping_vendor(): void
    {
        $owner = $this->owner('food@demo.hola');
        $category = $this->category('Food & Restaurants', 'ordering');
        $business = $this->verifiedBusiness($owner, $category, ['catalog' => true, 'orders' => true]);

        $this->seed(DemoCatalogSeeder::class);

        $this->assertTrue(ProductCategory::where('business_id', $business->id)->count() >= 5);
        $this->assertTrue(Product::where('business_id', $business->id)->count() >= 15);

        $product = Product::where('business_id', $business->id)->first();
        $this->assertNotNull($product);
        $this->assertEquals($business->id, $product->category->business_id);
        $this->assertTrue($product->is_active);
    }

    public function test_demo_catalog_seeder_creates_services_for_booking_vendor(): void
    {
        $owner = $this->owner('hotel@demo.hola');
        $category = $this->category('Hotels & Lodges', 'booking');
        $business = $this->verifiedBusiness($owner, $category, ['catalog' => true, 'bookings' => true]);

        $this->seed(DemoCatalogSeeder::class);

        $this->assertTrue(Service::where('business_id', $business->id)->count() >= 3);
    }

    public function test_demo_catalog_seeder_is_idempotent(): void
    {
        $owner = $this->owner('shop@demo.hola');
        $category = $this->category('Shopping & Retail', 'ordering');
        $business = $this->verifiedBusiness($owner, $category, ['catalog' => true, 'orders' => true]);

        $this->seed(DemoCatalogSeeder::class);
        $this->seed(DemoCatalogSeeder::class);

        $this->assertSame(
            ProductCategory::where('business_id', $business->id)->count(),
            ProductCategory::where('business_id', $business->id)->distinct('name')->count()
        );
    }

    public function test_demo_catalog_seeder_skips_non_demo_vendors(): void
    {
        $owner = User::factory()->create(['email' => 'real@business.com', 'role' => 'owner']);
        $category = $this->category('Food & Restaurants', 'ordering');
        $this->verifiedBusiness($owner, $category, ['catalog' => true, 'orders' => true]);

        $this->seed(DemoCatalogSeeder::class);

        $this->assertSame(0, ProductCategory::count());
        $this->assertSame(0, Product::count());
    }
}
