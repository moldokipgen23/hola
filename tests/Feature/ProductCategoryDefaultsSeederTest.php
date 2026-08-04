<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use Database\Seeders\ProductCategoryDefaultsSeeder;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryDefaultsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_seed_categories_and_subcategories_per_business_type(): void
    {
        $this->seed(WorldSeeder::class);
        $this->seed(ProductCategoryDefaultsSeeder::class);

        $medicineTop = ProductCategory::whereHas('businessType', fn ($q) => $q->where('slug', 'medicine'))
            ->whereNull('parent_id')
            ->pluck('name');

        $this->assertTrue($medicineTop->contains('Medicines'));
        $this->assertTrue($medicineTop->contains('First Aid'));

        $this->assertTrue(ProductCategory::whereHas('businessType', fn ($q) => $q->where('slug', 'grocery'))
            ->where('name', 'Rice & Dal')
            ->whereNotNull('parent_id')
            ->exists());

        $this->assertTrue(ProductCategory::whereHas('businessType', fn ($q) => $q->where('slug', 'medicine'))
            ->count() > 5);
    }

    public function test_defaults_seeder_is_idempotent(): void
    {
        $this->seed(WorldSeeder::class);
        $this->seed(ProductCategoryDefaultsSeeder::class);
        $count = ProductCategory::count();

        $this->seed(ProductCategoryDefaultsSeeder::class);

        $this->assertSame($count, ProductCategory::count());
    }
}
