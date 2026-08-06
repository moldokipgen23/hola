<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessClassification;
use App\Models\Category;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingBusinessTypesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require base_path('database/migrations/2026_08_06_000004_add_shopping_business_types.php');
        $migration->up();
    }

    private function shopWorld(): World
    {
        return World::create([
            'name' => 'Shopping', 'slug' => 'shop', 'is_active' => true, 'is_primary' => true,
        ]);
    }

    public function test_migrates_legacy_shop_taxonomy_into_clean_business_types(): void
    {
        $shop = $this->shopWorld();

        // Legacy shop roots already exist from the canonical taxonomy migration.
        $food = Category::where('slug', 'food-restaurants')->firstOrFail();
        $groceryStores = Category::where('slug', 'grocery-stores')->firstOrFail();

        // Businesses: one directly on a legacy root, one on a legacy child.
        $tasty = Business::create([
            'name' => 'Tasty Bites', 'slug' => 'tasty-bites-'.uniqid(), 'category_id' => $food->id, 'address' => 'x',
        ]);
        BusinessClassification::create([
            'business_id' => $tasty->id, 'category_id' => $food->id, 'is_primary' => true, 'is_active' => true,
        ]);

        $mart = Business::create([
            'name' => 'Quick Mart', 'slug' => 'quick-mart-'.uniqid(), 'category_id' => $groceryStores->id, 'address' => 'x',
        ]);
        BusinessClassification::create([
            'business_id' => $mart->id, 'category_id' => $groceryStores->id, 'is_primary' => true, 'is_active' => true,
        ]);

        $this->runMigration();

        $groceryRoot = Category::where('slug', 'grocery')->firstOrFail();
        $foodRoot = Category::where('slug', 'food')->firstOrFail();
        $medicineRoot = Category::where('slug', 'medicine')->firstOrFail();
        $generalRoot = Category::where('slug', 'general-shopping')->firstOrFail();

        foreach ([$groceryRoot, $foodRoot, $medicineRoot, $generalRoot] as $root) {
            $this->assertNull($root->parent_id);
            $this->assertSame(1, $root->level);
            $this->assertSame($shop->id, $root->world_id);
        }

        $this->assertSame($foodRoot->id, Category::where('slug', 'street-food')->value('parent_id'));
        $this->assertSame($groceryRoot->id, Category::where('slug', 'grocery-stores')->value('parent_id'));
        $this->assertSame($generalRoot->id, Category::where('slug', 'clothing')->value('parent_id'));
        $this->assertSame($generalRoot->id, Category::where('slug', 'mobile-shops')->value('parent_id'));
        $this->assertSame($medicineRoot->id, Category::where('slug', 'pharmacies')->value('parent_id'));

        $this->assertFalse((bool) Category::where('slug', 'food-restaurants')->value('is_active'));
        $this->assertFalse((bool) Category::where('slug', 'shopping-retail')->value('is_active'));
        $this->assertFalse((bool) Category::where('slug', 'electronics-tech')->value('is_active'));

        $this->assertSame($foodRoot->id, Business::find($tasty->id)->category_id);
        $this->assertSame(
            $foodRoot->id,
            BusinessClassification::where('business_id', $tasty->id)->where('is_primary', true)->value('category_id'),
        );

        $this->assertSame($groceryStores->id, Business::find($mart->id)->category_id);
        $this->assertNotNull(
            Category::where('slug', 'grocery')->where('id', $groceryRoot->id)->first(),
        );
    }

    public function test_migration_is_idempotent(): void
    {
        $this->shopWorld();

        $this->runMigration();
        $before = Category::count();
        $foodRoot = Category::where('slug', 'food')->firstOrFail();

        $this->runMigration();

        $this->assertSame($before, Category::count());
        $this->assertSame('Food', $foodRoot->fresh()->name);
        $this->assertSame($foodRoot->id, Category::where('slug', 'street-food')->value('parent_id'));
    }
}
