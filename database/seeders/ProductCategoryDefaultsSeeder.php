<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ProductCategory;
use App\Models\World;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Default admin-managed product categories per shopping business type.
 *
 * These live on the shop-world root categories (Restaurants, Grocery, Medicine,
 * Electronics, General) — NOT on individual businesses. Run with:
 *   php artisan db:seed --class=ProductCategoryDefaultsSeeder
 */
class ProductCategoryDefaultsSeeder extends Seeder
{
    private const DEFAULTS = [
        'medicine' => [
            'Medicines' => ['Tablets & Capsules', 'Syrups & Drops', 'Ointments & Creams'],
            'Vitamins & Supplements' => [],
            'First Aid' => ['Bandages & Dressings', 'Antiseptics'],
            'Baby Care' => [],
            'Personal Care' => ['Skin Care', 'Hair Care', 'Oral Care'],
        ],
        'grocery' => [
            'Fruits & Vegetables' => [],
            'Dairy & Eggs' => [],
            'Staples' => ['Rice & Dal', 'Oils & Ghee', 'Atta & Flours'],
            'Snacks & Beverages' => [],
            'Household Essentials' => ['Cleaning Supplies', 'Kitchen Accessories'],
        ],
        'restaurants' => [
            'Starters' => [],
            'Main Course' => [],
            'Beverages' => [],
            'Desserts' => [],
        ],
        'electronics' => [
            'Mobile & Accessories' => ['Mobile Phones', 'Chargers & Cables'],
            'Audio & Wearables' => ['Headphones', 'Smartwatches'],
            'Home Appliances' => [],
        ],
        'general' => [
            'Fashion & Apparel' => ['Men', 'Women', 'Kids'],
            'Stationery' => [],
            'Kitchen & Home' => [],
        ],
    ];

    public function run(): void
    {
        $shopWorld = World::where('slug', 'shop')->first();

        if (! $shopWorld) {
            $this->command?->warn('Shop world not found; skipping product category defaults.');

            return;
        }

        $types = Category::where('world_id', $shopWorld->id)
            ->whereNull('parent_id')
            ->get()
            ->keyBy('slug');

        foreach (self::DEFAULTS as $typeSlug => $groups) {
            $type = $types->get($typeSlug);

            if (! $type) {
                continue;
            }

            foreach ($groups as $groupName => $subNames) {
                $group = $this->category($type->id, null, $groupName);

                foreach ($subNames as $subName) {
                    $this->category($type->id, $group->id, $subName);
                }
            }
        }
    }

    private function category(int $businessTypeId, ?int $parentId, string $name): ProductCategory
    {
        return ProductCategory::updateOrCreate(
            ['business_type_id' => $businessTypeId, 'parent_id' => $parentId, 'name' => $name],
            [
                'slug' => Str::slug($name).'-'.Str::random(4),
                'sort_order' => 0,
                'is_active' => true,
            ],
        );
    }
}
