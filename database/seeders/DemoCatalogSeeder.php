<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = Business::whereHas('createdBy', fn ($q) => $q->where('email', 'like', '%@demo.hola'))
            ->where('verification_status', 'verified')
            ->with('category')
            ->get();

        if ($businesses->isEmpty()) {
            $this->command->error('No verified demo vendors found. Run DemoVendorsSeeder first.');

            return;
        }

        foreach ($businesses as $business) {
            $categoryName = $business->category?->name ?? '';
            $categoryType = $business->category?->module_type;

            if ($categoryType === 'booking') {
                $this->seedBookingServices($business);
            } elseif ($business->hasModule('catalog') || $business->hasModule('orders')) {
                $this->seedShoppingCatalog($business, $categoryName);
            }
        }
    }

    private function seedShoppingCatalog(Business $business, string $categoryName): void
    {
        if (ProductCategory::where('business_id', $business->id)->exists()) {
            $this->command->warn("Skipping {$business->name} — already has product categories.");

            return;
        }

        $catalog = $this->catalogFor($categoryName);

        foreach ($catalog as $section => $products) {
            $productCategory = ProductCategory::create([
                'business_id' => $business->id,
                'name' => $section,
                'slug' => Str::slug($business->name).'-'.Str::slug($section).'-'.Str::random(4),
                'is_active' => true,
            ]);

            foreach ($products as $product) {
                Product::create([
                    'business_id' => $business->id,
                    'product_category_id' => $productCategory->id,
                    'name' => $product['name'],
                    'slug' => Str::slug($product['name']).'-'.Str::random(4),
                    'description' => $product['description'] ?? null,
                    'price' => $product['price'],
                    'availability' => $product['availability'] ?? 'in_stock',
                    'stock' => $product['stock'] ?? null,
                    'is_active' => true,
                ]);
            }
        }

        $count = Product::where('business_id', $business->id)->count();
        $this->command->info("Seeded {$business->name}: ".count($catalog).' product categories, '.$count.' products.');
    }

    private function seedBookingServices(Business $business): void
    {
        if (Service::where('business_id', $business->id)->exists()) {
            $this->command->warn("Skipping {$business->name} — already has services.");

            return;
        }

        $services = $this->servicesFor($business->category?->name ?? '');

        foreach ($services as $service) {
            Service::create([
                'business_id' => $business->id,
                'name' => $service['name'],
                'description' => $service['description'] ?? null,
                'price' => $service['price'],
                'price_unit' => $service['price_unit'] ?? 'booking',
                'capacity' => $service['capacity'] ?? null,
                'duration' => $service['duration'] ?? null,
                'min_stay_nights' => $service['min_stay_nights'] ?? 1,
                'booking_mode' => $service['booking_mode'] ?? 'appointment',
                'is_active' => true,
            ]);
        }

        $this->command->info("Seeded {$business->name}: ".count($services).' services.');
    }

    private function catalogFor(string $categoryName): array
    {
        return match ($categoryName) {
            'Food & Restaurants' => [
                'Starters' => [
                    ['name' => 'Chicken Momo', 'price' => 80, 'description' => 'Steamed chicken dumplings with spicy chutney'],
                    ['name' => 'Veg Spring Roll', 'price' => 60],
                    ['name' => 'Alu Chop', 'price' => 30],
                ],
                'Main Course' => [
                    ['name' => 'Chicken Thali', 'price' => 150],
                    ['name' => 'Pork Curry with Rice', 'price' => 120],
                    ['name' => 'Fish Curry', 'price' => 100],
                    ['name' => 'Veg Thali', 'price' => 100],
                ],
                'Rice & Curry' => [
                    ['name' => 'Steamed Rice', 'price' => 40],
                    ['name' => 'Chicken Curry', 'price' => 110],
                    ['name' => 'Daal with Rice', 'price' => 70],
                ],
                'Snacks' => [
                    ['name' => 'Singju Salad', 'price' => 50],
                    ['name' => 'Chamthong', 'price' => 70],
                ],
                'Beverages' => [
                    ['name' => 'Green Tea', 'price' => 20],
                    ['name' => 'Masala Chai', 'price' => 20],
                    ['name' => 'Fresh Lime Soda', 'price' => 40],
                    ['name' => 'Coffee', 'price' => 30],
                ],
                'Desserts' => [
                    ['name' => 'Kheer', 'price' => 50],
                    ['name' => 'Ice Cream', 'price' => 40],
                ],
            ],
            'Shopping & Retail' => [
                'Groceries' => [
                    ['name' => 'Rice 5kg', 'price' => 350, 'stock' => 50],
                    ['name' => 'Wheat Atta 5kg', 'price' => 250, 'stock' => 40],
                    ['name' => 'Moong Daal 1kg', 'price' => 120, 'stock' => 60],
                    ['name' => 'Cooking Oil 1L', 'price' => 140, 'stock' => 45],
                    ['name' => 'Sugar 1kg', 'price' => 45, 'stock' => 80],
                    ['name' => 'Salt 1kg', 'price' => 20, 'stock' => 100],
                ],
                'Snacks & Beverages' => [
                    ['name' => 'Potato Chips', 'price' => 20, 'stock' => 90],
                    ['name' => 'Biscuits', 'price' => 20, 'stock' => 90],
                    ['name' => 'Cold Drink 750ml', 'price' => 35, 'stock' => 60],
                    ['name' => 'Instant Noodles', 'price' => 15, 'stock' => 70],
                ],
                'Household Essentials' => [
                    ['name' => 'Detergent 1kg', 'price' => 90, 'stock' => 40],
                    ['name' => 'Dish Wash Liquid', 'price' => 60, 'stock' => 50],
                    ['name' => 'Toothpaste', 'price' => 50, 'stock' => 65],
                    ['name' => 'Bath Soap', 'price' => 35, 'stock' => 80],
                ],
                'Personal Care' => [
                    ['name' => 'Shampoo 200ml', 'price' => 120, 'stock' => 35],
                    ['name' => 'Handwash 250ml', 'price' => 80, 'stock' => 45],
                ],
                'Dairy & Bakery' => [
                    ['name' => 'Milk 1L', 'price' => 30, 'stock' => 50],
                    ['name' => 'Bread', 'price' => 35, 'stock' => 40],
                ],
            ],
            default => [
                'General' => [
                    ['name' => 'Featured Item', 'price' => 100, 'stock' => 20],
                ],
            ],
        };
    }

    private function servicesFor(string $categoryName): array
    {
        return match ($categoryName) {
            'Hotels & Lodges' => [
                ['name' => 'Standard Room', 'description' => 'Single occupancy room with basic amenities', 'price' => 1200, 'price_unit' => 'per_night', 'capacity' => 2, 'min_stay_nights' => 1],
                ['name' => 'Deluxe Room', 'description' => 'Spacious room with city view', 'price' => 1800, 'price_unit' => 'per_night', 'capacity' => 2, 'min_stay_nights' => 1],
                ['name' => 'Family Suite', 'description' => 'Two-bed suite ideal for families', 'price' => 2500, 'price_unit' => 'per_night', 'capacity' => 4, 'min_stay_nights' => 1],
            ],
            default => [
                ['name' => 'Standard Service', 'description' => 'Bookable service', 'price' => 500, 'price_unit' => 'per_session', 'duration' => 60],
            ],
        };
    }
}
