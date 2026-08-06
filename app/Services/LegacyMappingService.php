<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\World;

class LegacyMappingService
{
    private const WORLD_MAP = [
        'shop' => [
            'name' => 'Shop',
            'slug' => 'shop',
            'icon' => 'store',
            'categories' => ['Food', 'Shopping', 'Electronics', 'Fashion', 'Healthcare', 'Education'],
            'business_types' => ['ordering', 'both'],
        ],
        'ride' => [
            'name' => 'Ride',
            'slug' => 'ride',
            'icon' => 'directions_car',
            'categories' => ['Automotive', 'Transport'],
            'business_types' => ['transport'],
        ],
        'book' => [
            'name' => 'Book',
            'slug' => 'book',
            'icon' => 'calendar_today',
            'categories' => ['Hotels', 'Healthcare', 'Education', 'Professional Services', 'Sports & Entertainment'],
            'business_types' => ['booking', 'turf'],
        ],
        'discover' => [
            'name' => 'Discover',
            'slug' => 'discover',
            'icon' => 'explore',
            'categories' => [],
            'business_types' => ['directory'],
        ],
    ];

    public function mapBusinessToWorld(Business $business): ?string
    {
        $modules = $business->effectiveModules();

        if ($modules['transport']) {
            return 'ride';
        }

        if ($modules['turf'] || $modules['bookings']) {
            return 'book';
        }

        if ($modules['catalog'] || $modules['orders']) {
            return 'shop';
        }

        return 'discover';
    }

    public function mapCategoryToWorld(Category $category): ?string
    {
        if ($category->world_id) {
            return $category->world?->slug;
        }

        $moduleName = $category->module_type ?? 'directory';

        return match ($moduleName) {
            'ordering', 'both' => 'shop',
            'transport' => 'ride',
            'booking', 'turf' => 'book',
            default => 'discover',
        };
    }

    public function migrateBusiness(Business $business): void
    {
        $worldSlug = $this->mapBusinessToWorld($business);
        if (! $worldSlug) {
            return;
        }

        $world = World::where('slug', $worldSlug)->first();
        if (! $world) {
            return;
        }

        $categoryId = $business->category_id;
        if ($categoryId && ! $business->classifications()->where('category_id', $categoryId)->exists()) {
            $business->classifications()->create([
                'category_id' => $categoryId,
                'is_primary' => true,
                'source' => 'system_migrated',
            ]);
        }

        $subcategoryId = $business->subcategory_id;
        if ($subcategoryId && ! $business->classifications()->where('category_id', $subcategoryId)->exists()) {
            $subcategory = Subcategory::find($subcategoryId);
            if ($subcategory) {
                $parentCategory = $subcategory->category;
                if ($parentCategory && ! $business->classifications()->where('category_id', $parentCategory->id)->exists()) {
                    $business->classifications()->create([
                        'category_id' => $parentCategory->id,
                        'is_primary' => false,
                        'source' => 'system_migrated',
                    ]);
                }
            }
        }
    }

    public function migrateAll(): int
    {
        $count = 0;
        $businesses = Business::with(['category', 'subcategory'])->cursor();

        foreach ($businesses as $business) {
            $this->migrateBusiness($business);
            $count++;
        }

        return $count;
    }
}
