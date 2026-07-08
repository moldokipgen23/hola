<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Admin User ───
        User::create([
            'name' => 'Admin',
            'email' => 'admin@hola.app',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // ─── Categories ───
        $categories = [
            ['name' => 'Food & Restaurants', 'icon' => '🍽️', 'is_featured' => true, 'order' => 1],
            ['name' => 'Hotels & Lodges', 'icon' => '🏨', 'is_featured' => true, 'order' => 2],
            ['name' => 'Healthcare', 'icon' => '🏥', 'is_featured' => true, 'order' => 3],
            ['name' => 'Education', 'icon' => '📚', 'is_featured' => true, 'order' => 4],
            ['name' => 'Shopping & Retail', 'icon' => '🛍️', 'is_featured' => true, 'order' => 5],
            ['name' => 'Electronics & Tech', 'icon' => '💻', 'is_featured' => true, 'order' => 6],
            ['name' => 'Automobiles', 'icon' => '🚗', 'is_featured' => false, 'order' => 7],
            ['name' => 'Beauty & Wellness', 'icon' => '💆', 'is_featured' => false, 'order' => 8],
            ['name' => 'Professional Services', 'icon' => '💼', 'is_featured' => false, 'order' => 9],
            ['name' => 'Sports & Fitness', 'icon' => '🏋️', 'is_featured' => false, 'order' => 10],
        ];

        $catModels = [];
        foreach ($categories as $cat) {
            $catModels[$cat['name']] = Category::create([
                ...$cat,
                'slug' => Str::slug($cat['name']),
            ]);
        }

        // ─── Subcategories ───
        $subcategories = [
            'Food & Restaurants' => [
                ['name' => 'Restaurants', 'icon' => '🍴', 'order' => 1],
                ['name' => 'Street Food', 'icon' => '🌮', 'order' => 2],
                ['name' => 'Bakeries', 'icon' => '🧁', 'order' => 3],
                ['name' => 'Cafes', 'icon' => '☕', 'order' => 4],
                ['name' => 'Fast Food', 'icon' => '🍔', 'order' => 5],
            ],
            'Hotels & Lodges' => [
                ['name' => 'Hotels', 'icon' => '🛏️', 'order' => 1],
                ['name' => 'Guest Houses', 'icon' => '🏡', 'order' => 2],
                ['name' => 'Homestays', 'icon' => '🏠', 'order' => 3],
            ],
            'Healthcare' => [
                ['name' => 'Hospitals', 'icon' => '🏥', 'order' => 1],
                ['name' => 'Pharmacies', 'icon' => '💊', 'order' => 2],
                ['name' => 'Clinics', 'icon' => '🩺', 'order' => 3],
                ['name' => 'Dental', 'icon' => '🦷', 'order' => 4],
            ],
            'Education' => [
                ['name' => 'Schools', 'icon' => '🏫', 'order' => 1],
                ['name' => 'Colleges', 'icon' => '🎓', 'order' => 2],
                ['name' => 'Tuition Centers', 'icon' => '📝', 'order' => 3],
            ],
            'Shopping & Retail' => [
                ['name' => 'Grocery Stores', 'icon' => '🛒', 'order' => 1],
                ['name' => 'Clothing', 'icon' => '👕', 'order' => 2],
                ['name' => 'Hardware Stores', 'icon' => '🔨', 'order' => 3],
                ['name' => 'Stationery', 'icon' => '✏️', 'order' => 4],
            ],
            'Electronics & Tech' => [
                ['name' => 'Mobile Shops', 'icon' => '📱', 'order' => 1],
                ['name' => 'Computer Stores', 'icon' => '🖥️', 'order' => 2],
                ['name' => 'Repair Shops', 'icon' => '🔧', 'order' => 3],
            ],
            'Automobiles' => [
                ['name' => 'Car Dealers', 'icon' => '🚘', 'order' => 1],
                ['name' => 'Bike Shops', 'icon' => '🏍️', 'order' => 2],
                ['name' => 'Service Centers', 'icon' => '🔧', 'order' => 3],
            ],
            'Beauty & Wellness' => [
                ['name' => 'Salons', 'icon' => '💇', 'order' => 1],
                ['name' => 'Spas', 'icon' => '🧖', 'order' => 2],
                ['name' => 'Beauty Parlours', 'icon' => '💄', 'order' => 3],
            ],
            'Professional Services' => [
                ['name' => 'Banks', 'icon' => '🏦', 'order' => 1],
                ['name' => 'Insurance', 'icon' => '📋', 'order' => 2],
                ['name' => 'Legal Services', 'icon' => '⚖️', 'order' => 3],
                ['name' => 'Travel Agents', 'icon' => '✈️', 'order' => 4],
            ],
            'Sports & Fitness' => [
                ['name' => 'Gyms', 'icon' => '💪', 'order' => 1],
                ['name' => 'Sports Shops', 'icon' => '⚽', 'order' => 2],
            ],
        ];

        $subModels = [];
        foreach ($subcategories as $catName => $subs) {
            foreach ($subs as $sub) {
                $subModels[$sub['name']] = Subcategory::create([
                    'category_id' => $catModels[$catName]->id,
                    ...$sub,
                    'slug' => Str::slug($sub['name']),
                ]);
            }
        }

        // ─── Businesses ───
        $businesses = [
            // Food & Restaurants
            ['name' => 'Lamka Kitchen', 'category' => 'Food & Restaurants', 'subcategory' => 'Restaurants', 'address' => 'Thanlon Road, Lamka', 'phone' => '9876543210', 'description' => 'Authentic Manipuri and tribal cuisine. Known for their Eromba and fish dishes.', 'is_featured' => true, 'verification_status' => 'verified', 'latitude' => 24.3667, 'longitude' => 93.7000, 'working_hours' => ['mon' => '10:00-22:00', 'tue' => '10:00-22:00', 'wed' => '10:00-22:00', 'thu' => '10:00-22:00', 'fri' => '10:00-22:00', 'sat' => '10:00-22:00', 'sun' => '12:00-20:00']],
            ['name' => 'Meitei Restaurant', 'category' => 'Food & Restaurants', 'subcategory' => 'Restaurants', 'address' => 'Zou Road, Lamka', 'phone' => '9876543211', 'description' => 'Traditional Meitei thali and local delicacies. Family friendly.', 'is_featured' => true, 'verification_status' => 'verified', 'latitude' => 24.3670, 'longitude' => 93.7010],
            ['name' => 'Churachandpur Biryani House', 'category' => 'Food & Restaurants', 'subcategory' => 'Fast Food', 'address' => 'New Lamka', 'phone' => '9876543212', 'description' => 'Best biryani in town. Also serves kebabs and tandoori items.', 'latitude' => 24.3650, 'longitude' => 93.6980],
            ['name' => 'Happy Bakery', 'category' => 'Food & Restaurants', 'subcategory' => 'Bakeries', 'address' => 'Songtal Road', 'phone' => '9876543213', 'description' => 'Fresh bread, cakes, and pastries daily. Custom orders available.', 'is_featured' => true, 'latitude' => 24.3660, 'longitude' => 93.6990, 'working_hours' => ['mon' => '06:00-21:00', 'tue' => '06:00-21:00', 'wed' => '06:00-21:00', 'thu' => '06:00-21:00', 'fri' => '06:00-21:00', 'sat' => '06:00-21:00', 'sun' => '07:00-18:00']],
            ['name' => 'Morning Coffee Cafe', 'category' => 'Food & Restaurants', 'subcategory' => 'Cafes', 'address' => 'Tuibong Road', 'phone' => '9876543214', 'description' => 'Cozy cafe with WiFi. Great coffee and light snacks.', 'latitude' => 24.3655, 'longitude' => 93.7005],
            ['name' => 'Khangkha Street Food', 'category' => 'Food & Restaurants', 'subcategory' => 'Street Food', 'address' => 'Main Bazaar', 'phone' => '9876543215', 'description' => 'Popular street food stall. Momos, chowmein, and local snacks.', 'latitude' => 24.3662, 'longitude' => 93.6995],
            ['name' => 'Zo Asian Kitchen', 'category' => 'Food & Restaurants', 'subcategory' => 'Restaurants', 'address' => 'Hmar Veng', 'phone' => '9876543216', 'description' => 'Zou and Chin cuisine specialties. Cultural dining experience.', 'latitude' => 24.3675, 'longitude' => 93.7015],
            ['name' => 'Pizza Corner Lamka', 'category' => 'Food & Restaurants', 'subcategory' => 'Fast Food', 'address' => 'Lamka Central', 'phone' => '9876543217', 'description' => 'Pizza, pasta, and burgers. Delivery available.', 'latitude' => 24.3668, 'longitude' => 93.7008],

            // Hotels & Lodges
            ['name' => 'Hotel Lamka Palace', 'category' => 'Hotels & Lodges', 'subcategory' => 'Hotels', 'address' => 'Thanlon Road, Lamka', 'phone' => '9876543220', 'description' => 'Premium hotel with AC rooms, restaurant, and conference hall.', 'is_featured' => true, 'verification_status' => 'verified', 'latitude' => 24.3671, 'longitude' => 93.7012, 'working_hours' => ['mon' => '00:00-23:59', 'tue' => '00:00-23:59', 'wed' => '00:00-23:59', 'thu' => '00:00-23:59', 'fri' => '00:00-23:59', 'sat' => '00:00-23:59', 'sun' => '00:00-23:59']],
            ['name' => 'Churachandpur Guest House', 'category' => 'Hotels & Lodges', 'subcategory' => 'Guest Houses', 'address' => 'New Lamka', 'phone' => '9876543221', 'description' => 'Affordable guest house with clean rooms and friendly staff.', 'latitude' => 24.3653, 'longitude' => 93.6982],
            ['name' => 'Hills View Homestay', 'category' => 'Hotels & Lodges', 'subcategory' => 'Homestays', 'address' => 'Lamka Hills', 'phone' => '9876543222', 'description' => 'Experience local life. Home-cooked meals included.', 'latitude' => 24.3680, 'longitude' => 93.7020],
            ['name' => 'Royal Inn', 'category' => 'Hotels & Lodges', 'subcategory' => 'Hotels', 'address' => 'Zou Road', 'phone' => '9876543223', 'description' => 'Mid-range hotel with parking and room service.', 'latitude' => 24.3665, 'longitude' => 93.7002],

            // Healthcare
            ['name' => 'CHC Lamka', 'category' => 'Healthcare', 'subcategory' => 'Hospitals', 'address' => 'Lamka Hospital Road', 'phone' => '9876543230', 'description' => 'Primary health center with emergency services and OPD.', 'is_featured' => true, 'verification_status' => 'verified', 'latitude' => 24.3672, 'longitude' => 93.7007, 'working_hours' => ['mon' => '00:00-23:59', 'tue' => '00:00-23:59', 'wed' => '00:00-23:59', 'thu' => '00:00-23:59', 'fri' => '00:00-23:59', 'sat' => '00:00-23:59', 'sun' => '00:00-23:59']],
            ['name' => 'MedPlus Pharmacy', 'category' => 'Healthcare', 'subcategory' => 'Pharmacies', 'address' => 'Main Bazaar', 'phone' => '9876543231', 'description' => 'All types of medicines available. Home delivery within Lamka.', 'latitude' => 24.3663, 'longitude' => 93.6997],
            ['name' => 'Lamka Dental Clinic', 'category' => 'Healthcare', 'subcategory' => 'Dental', 'address' => 'Tuibong Road', 'phone' => '9876543232', 'description' => 'Modern dental care. Braces, root canal, and implants.', 'latitude' => 24.3657, 'longitude' => 93.7003],
            ['name' => 'HealthFirst Clinic', 'category' => 'Healthcare', 'subcategory' => 'Clinics', 'address' => 'Songtal Road', 'phone' => '9876543233', 'description' => 'General physician and specialist consultations.', 'latitude' => 24.3661, 'longitude' => 93.6992],
            ['name' => 'Apollo Pharmacy', 'category' => 'Healthcare', 'subcategory' => 'Pharmacies', 'address' => 'New Lamka', 'phone' => '9876543234', 'description' => 'Branded pharmacy chain. Genuine medicines guaranteed.', 'latitude' => 24.3652, 'longitude' => 93.6985],

            // Education
            ['name' => 'Lamka Higher Secondary School', 'category' => 'Education', 'subcategory' => 'Schools', 'address' => 'Lamka Central', 'phone' => '9876543240', 'description' => 'Government school with science, arts, and commerce streams.', 'latitude' => 24.3669, 'longitude' => 93.7006],
            ['name' => 'GP Women\'s College', 'category' => 'Education', 'subcategory' => 'Colleges', 'address' => 'Tuibong', 'phone' => '9876543241', 'description' => 'Degree college for women. Arts, Science, and Commerce.', 'latitude' => 24.3656, 'longitude' => 93.7001],
            ['name' => 'Bright Future Tuition Center', 'category' => 'Education', 'subcategory' => 'Tuition Centers', 'address' => 'New Lamka', 'phone' => '9876543242', 'description' => 'Classes for Class 10 and 12 board exam preparation.', 'latitude' => 24.3651, 'longitude' => 93.6983],
            ['name' => 'St. Paul\'s School', 'category' => 'Education', 'subcategory' => 'Schools', 'address' => 'Zou Road', 'phone' => '9876543243', 'description' => 'English medium school. Nursery to Class 12.', 'latitude' => 24.3664, 'longitude' => 93.7004],

            // Shopping & Retail
            ['name' => 'Lamka Supermarket', 'category' => 'Shopping & Retail', 'subcategory' => 'Grocery Stores', 'address' => 'Main Bazaar', 'phone' => '9876543250', 'description' => 'One-stop shop for groceries, FMCG, and household items.', 'is_featured' => true, 'latitude' => 24.3662, 'longitude' => 93.6996, 'working_hours' => ['mon' => '08:00-21:00', 'tue' => '08:00-21:00', 'wed' => '08:00-21:00', 'thu' => '08:00-21:00', 'fri' => '08:00-21:00', 'sat' => '08:00-21:00', 'sun' => '09:00-18:00']],
            ['name' => 'Tribal Fashion Hub', 'category' => 'Shopping & Retail', 'subcategory' => 'Clothing', 'address' => 'Zou Road', 'phone' => '9876543251', 'description' => 'Traditional and modern clothing. Tribal attire speciality.', 'latitude' => 24.3666, 'longitude' => 93.7009],
            ['name' => 'Hardware World', 'category' => 'Shopping & Retail', 'subcategory' => 'Hardware Stores', 'address' => 'Thanlon Road', 'phone' => '9876543252', 'description' => 'All construction materials, tools, and plumbing supplies.', 'latitude' => 24.3673, 'longitude' => 93.7013],
            ['name' => 'Book Corner', 'category' => 'Shopping & Retail', 'subcategory' => 'Stationery', 'address' => 'Lamka Central', 'phone' => '9876543253', 'description' => 'Books, notebooks, art supplies, and school materials.', 'latitude' => 24.3668, 'longitude' => 93.7007],
            ['name' => 'New Lamka Mart', 'category' => 'Shopping & Retail', 'subcategory' => 'Grocery Stores', 'address' => 'New Lamka', 'phone' => '9876543254', 'description' => 'Fresh vegetables, fruits, and daily essentials.', 'latitude' => 24.3650, 'longitude' => 93.6981],

            // Electronics & Tech
            ['name' => 'Mobile World Lamka', 'category' => 'Electronics & Tech', 'subcategory' => 'Mobile Shops', 'address' => 'Main Bazaar', 'phone' => '9876543260', 'description' => 'All mobile brands. Accessories and repair services.', 'is_featured' => true, 'latitude' => 24.3661, 'longitude' => 93.6998],
            ['name' => 'Computer Hub', 'category' => 'Electronics & Tech', 'subcategory' => 'Computer Stores', 'address' => 'Tuibong Road', 'phone' => '9876543261', 'description' => 'Laptops, desktops, printers, and networking equipment.', 'latitude' => 24.3658, 'longitude' => 93.7000],
            ['name' => 'Quick Fix Repair', 'category' => 'Electronics & Tech', 'subcategory' => 'Repair Shops', 'address' => 'Songtal Road', 'phone' => '9876543262', 'description' => 'Mobile, laptop, and tablet repair. Same day service.', 'latitude' => 24.3660, 'longitude' => 93.6993],
            ['name' => 'Digital Zone', 'category' => 'Electronics & Tech', 'subcategory' => 'Mobile Shops', 'address' => 'New Lamka', 'phone' => '9876543263', 'description' => 'Budget smartphones and accessories. EMI available.', 'latitude' => 24.3653, 'longitude' => 93.6984],

            // Automobiles
            ['name' => 'Lamka Auto Dealers', 'category' => 'Automobiles', 'subcategory' => 'Car Dealers', 'address' => 'Thanlon Road', 'phone' => '9876543270', 'description' => 'New and used car sales. Maruti, Hyundai, Tata authorized.', 'latitude' => 24.3674, 'longitude' => 93.7014],
            ['name' => 'Two Wheeler Zone', 'category' => 'Automobiles', 'subcategory' => 'Bike Shops', 'address' => 'Zou Road', 'phone' => '9876543271', 'description' => 'Honda, Hero, Bajaj bikes. Service and spare parts.', 'latitude' => 24.3667, 'longitude' => 93.7011],
            ['name' => 'Auto Care Service Center', 'category' => 'Automobiles', 'subcategory' => 'Service Centers', 'address' => 'Lamka Industrial Area', 'phone' => '9876543272', 'description' => 'All car and bike servicing. Washing and detailing.', 'latitude' => 24.3682, 'longitude' => 93.7025],

            // Beauty & Wellness
            ['name' => 'Lamka Beauty Parlour', 'category' => 'Beauty & Wellness', 'subcategory' => 'Beauty Parlours', 'address' => 'Main Bazaar', 'phone' => '9876543280', 'description' => 'Bridal makeup, hair styling, and skin care treatments.', 'latitude' => 24.3663, 'longitude' => 93.6999],
            ['name' => 'Gents Salon & Spa', 'category' => 'Beauty & Wellness', 'subcategory' => 'Salons', 'address' => 'Tuibong Road', 'phone' => '9876543281', 'description' => 'Men\'s grooming, haircut, and massage services.', 'latitude' => 24.3659, 'longitude' => 93.7002],
            ['name' => 'Relax Spa Center', 'category' => 'Beauty & Wellness', 'subcategory' => 'Spas', 'address' => 'Songtal Road', 'phone' => '9876543282', 'description' => 'Traditional oil massage and body spa treatments.', 'latitude' => 24.3662, 'longitude' => 93.6994],

            // Professional Services
            ['name' => 'SBI Lamka Branch', 'category' => 'Professional Services', 'subcategory' => 'Banks', 'address' => 'Lamka Central', 'phone' => '9876543290', 'description' => 'Full banking services. ATM available 24/7.', 'is_featured' => true, 'latitude' => 24.3669, 'longitude' => 93.7008, 'working_hours' => ['mon' => '10:00-16:00', 'tue' => '10:00-16:00', 'wed' => '10:00-16:00', 'thu' => '10:00-16:00', 'fri' => '10:00-16:00', 'sat' => 'CLOSED', 'sun' => 'CLOSED']],
            ['name' => 'North East Insurance', 'category' => 'Professional Services', 'subcategory' => 'Insurance', 'address' => 'Zou Road', 'phone' => '9876543291', 'description' => 'Life, health, and vehicle insurance. LIC and private.', 'latitude' => 24.3665, 'longitude' => 93.7005],
            ['name' => 'Justice Legal Associates', 'category' => 'Professional Services', 'subcategory' => 'Legal Services', 'address' => 'Tuibong Road', 'phone' => '9876543292', 'description' => 'Legal consultancy, property documents, and court cases.', 'latitude' => 24.3657, 'longitude' => 93.7001],
            ['name' => 'NE Travels', 'category' => 'Professional Services', 'subcategory' => 'Travel Agents', 'address' => 'Main Bazaar', 'phone' => '9876543293', 'description' => 'Flight booking, tour packages, and hotel reservations.', 'latitude' => 24.3664, 'longitude' => 93.6996],

            // Sports & Fitness
            ['name' => 'Iron Gym Lamka', 'category' => 'Sports & Fitness', 'subcategory' => 'Gyms', 'address' => 'New Lamka', 'phone' => '9876543300', 'description' => 'Modern gym with trainer. Monthly and annual plans.', 'latitude' => 24.3654, 'longitude' => 93.6986],
            ['name' => 'Sports Corner', 'category' => 'Sports & Fitness', 'subcategory' => 'Sports Shops', 'address' => 'Main Bazaar', 'phone' => '9876543301', 'description' => 'Cricket, football, and fitness equipment. Jerseys available.', 'latitude' => 24.3663, 'longitude' => 93.6997],
        ];

        $businessModels = [];
        foreach ($businesses as $biz) {
            $cat = $catModels[$biz['category']] ?? null;
            $sub = $subModels[$biz['subcategory']] ?? null;

            $businessModels[$biz['name']] = Business::create([
                'name' => $biz['name'],
                'slug' => Str::slug($biz['name']),
                'category_id' => $cat->id,
                'subcategory_id' => $sub?->id,
                'description' => $biz['description'] ?? null,
                'address' => $biz['address'],
                'district' => 'Churachandpur',
                'latitude' => $biz['latitude'] ?? null,
                'longitude' => $biz['longitude'] ?? null,
                'phone' => $biz['phone'] ?? null,
                'whatsapp' => $biz['phone'] ?? null,
                'working_hours' => $biz['working_hours'] ?? null,
                'is_featured' => $biz['is_featured'] ?? false,
                'is_active' => true,
                'verification_status' => $biz['verification_status'] ?? 'pending',
                'views_count' => rand(50, 500),
                'saves_count' => rand(5, 50),
            ]);
        }

        // ─── Products ───
        $products = [
            ['business' => 'Lamka Kitchen', 'name' => 'Eromba', 'description' => 'Traditional Manipuri fermented fish dish', 'price' => 150, 'availability' => 'in_stock'],
            ['business' => 'Lamka Kitchen', 'name' => 'Fish Thali', 'description' => 'Full meal with rice, fish curry, and sides', 'price' => 250, 'availability' => 'in_stock'],
            ['business' => 'Lamka Kitchen', 'name' => 'Chakhao Kheer', 'description' => 'Black rice pudding dessert', 'price' => 80, 'availability' => 'in_stock'],
            ['business' => 'Happy Bakery', 'name' => 'Chocolate Cake', 'description' => 'Freshly baked chocolate truffle cake', 'price' => 500, 'availability' => 'in_stock'],
            ['business' => 'Happy Bakery', 'name' => 'Samosa (4 pcs)', 'description' => 'Crispy vegetable samosas', 'price' => 40, 'availability' => 'in_stock'],
            ['business' => 'Happy Bakery', 'name' => 'White Bread', 'description' => 'Fresh white bread loaf', 'price' => 35, 'availability' => 'in_stock'],
            ['business' => 'Mobile World Lamka', 'name' => 'iPhone 15', 'description' => 'Apple iPhone 15 128GB', 'price' => 79900, 'availability' => 'in_stock'],
            ['business' => 'Mobile World Lamka', 'name' => 'Samsung Galaxy S24', 'description' => 'Samsung flagship smartphone', 'price' => 64999, 'availability' => 'in_stock'],
            ['business' => 'Mobile World Lamka', 'name' => 'OnePlus 12', 'description' => 'OnePlus premium phone', 'price' => 54999, 'availability' => 'in_stock'],
            ['business' => 'Lamka Supermarket', 'name' => 'Basmati Rice (5kg)', 'description' => 'Premium quality basmati rice', 'price' => 450, 'availability' => 'in_stock'],
            ['business' => 'Lamka Supermarket', 'name' => 'Mustard Oil (1L)', 'description' => 'Pure mustard oil', 'price' => 180, 'availability' => 'in_stock'],
            ['business' => 'Lamka Supermarket', 'name' => 'Sugar (1kg)', 'description' => 'Refined white sugar', 'price' => 50, 'availability' => 'in_stock'],
            ['business' => 'MedPlus Pharmacy', 'name' => 'Paracetamol', 'description' => 'Fever and pain relief tablets', 'price' => 25, 'availability' => 'in_stock'],
            ['business' => 'MedPlus Pharmacy', 'name' => 'ORS Packets', 'description' => 'Oral rehydration salts', 'price' => 15, 'availability' => 'in_stock'],
            ['business' => 'Sports Corner', 'name' => 'Cricket Bat (SG)', 'description' => 'Professional grade cricket bat', 'price' => 2500, 'availability' => 'in_stock'],
            ['business' => 'Sports Corner', 'name' => 'Football (Nivia)', 'description' => 'Size 5 football', 'price' => 800, 'availability' => 'in_stock'],
            ['business' => 'Computer Hub', 'name' => 'HP Laptop 15s', 'description' => 'Intel i5, 8GB RAM, 512GB SSD', 'price' => 45999, 'availability' => 'in_stock'],
            ['business' => 'Computer Hub', 'name' => 'Canon Printer', 'description' => 'All-in-one inkjet printer', 'price' => 8999, 'availability' => 'in_stock'],
            ['business' => 'Two Wheeler Zone', 'name' => 'Honda SP 125', 'description' => 'Honda commuter motorcycle', 'price' => 82000, 'availability' => 'in_stock'],
            ['business' => 'Two Wheeler Zone', 'name' => 'Hero Splendor Plus', 'description' => 'India\'s best selling motorcycle', 'price' => 75000, 'availability' => 'in_stock'],
        ];

        foreach ($products as $prod) {
            $biz = $businessModels[$prod['business']] ?? null;
            if ($biz) {
                Product::create([
                    'business_id' => $biz->id,
                    'name' => $prod['name'],
                    'slug' => Str::slug($prod['name']),
                    'description' => $prod['description'],
                    'price' => $prod['price'],
                    'availability' => $prod['availability'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
