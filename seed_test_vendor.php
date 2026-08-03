<?php

use App\Models\User;
use App\Models\Business;
use App\Models\Product;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\Vehicle;
use App\Models\Category;
use Illuminate\Support\Str;

$user = User::create([
    'name' => 'Test Vendor',
    'email' => 'vendor@test.com',
    'password' => bcrypt('Test@2026'),
    'phone' => '9876543210',
    'role' => 'owner',
]);
echo "User ID: {$user->id}\n";

$cat = Category::firstOrCreate(['name' => 'Restaurant'], ['slug' => 'restaurant', 'module_type' => 'catalog']);

$biz = Business::create([
    'name' => 'Test Restaurant & Shop',
    'description' => 'A test business with all features enabled',
    'address' => 'Lamka, Churachandpur, Manipur',
    'phone' => '9876543210',
    'whatsapp' => '9876543210',
    'email' => 'test@business.com',
    'is_active' => true,
    'created_by' => $user->id,
    'category_id' => $cat->id,
    'enabled_modules' => ['catalog' => true, 'orders' => true, 'bookings' => true, 'inventory' => true, 'transport' => true, 'turf' => true],
    'enabled_experiences' => ['directory', 'retail', 'restaurant', 'appointment', 'stay', 'turf', 'taxi', 'shared_transport', 'vehicle_rental', 'goods_transport', 'seat_event'],
    'primary_experience' => 'restaurant',
    'service_type' => 'hybrid',
    'is_bookable' => true,
]);
echo "Business ID: {$biz->id}\n";

for ($i = 1; $i <= 5; $i++) {
    Product::create([
        'business_id' => $biz->id,
        'name' => 'Test Product ' . $i,
        'description' => 'Delicious test product #' . $i,
        'price' => $i * 50,
        'slug' => 'test-product-' . $i . '-' . Str::random(5),
        'is_active' => true,
        'menu_section' => 'General',
    ]);
}
echo "5 products created\n";

$svc1 = Service::create([
    'business_id' => $biz->id,
    'name' => 'Consultation',
    'description' => 'General consultation',
    'price' => 200, 'price_unit' => 'booking', 'booking_mode' => 'appointment',
    'duration' => 30, 'capacity' => 5, 'inventory_units' => 5, 'is_active' => true,
    'slug' => 'consultation-' . Str::random(5),
]);

$svc2 = Service::create([
    'business_id' => $biz->id,
    'name' => 'Cricket Turf',
    'description' => 'Cricket ground slot',
    'price' => 500, 'price_unit' => 'booking', 'booking_mode' => 'slot',
    'duration' => 60, 'capacity' => 2, 'has_fixed_slots' => true, 'is_active' => true,
    'slug' => 'cricket-turf-' . Str::random(5),
]);

$svc3 = Service::create([
    'business_id' => $biz->id,
    'name' => 'Deluxe Room',
    'description' => 'AC room with view',
    'price' => 1200, 'price_unit' => 'night', 'booking_mode' => 'stay',
    'inventory_units' => 3, 'check_in_time' => '14:00', 'check_out_time' => '11:00',
    'min_stay_nights' => 1, 'max_stay_nights' => 30, 'is_active' => true,
    'slug' => 'deluxe-room-' . Str::random(5),
]);

$svc4 = Service::create([
    'business_id' => $biz->id,
    'name' => 'Concert Tickets',
    'description' => 'Live music event',
    'price' => 300, 'price_unit' => 'seat', 'booking_mode' => 'seat',
    'capacity' => 100, 'unit_label' => 'seat', 'is_active' => true,
    'slug' => 'concert-' . Str::random(5),
]);
echo "4 services created\n";

for ($day = 1; $day <= 5; $day++) {
    foreach (['06:00-07:00', '07:00-08:00', '17:00-18:00'] as $slot) {
        [$start, $end] = explode('-', $slot);
        TimeSlot::create([
            'service_id' => $svc2->id, 'day_of_week' => $day,
            'start_time' => $start, 'end_time' => $end, 'capacity' => 2, 'is_active' => true,
        ]);
    }
    foreach (['09:00-09:30', '10:00-10:30', '14:00-14:30', '16:00-16:30'] as $slot) {
        [$start, $end] = explode('-', $slot);
        TimeSlot::create([
            'service_id' => $svc1->id, 'day_of_week' => $day,
            'start_time' => $start, 'end_time' => $end, 'capacity' => 5, 'is_active' => true,
        ]);
    }
}
echo "Time slots created\n";

Vehicle::create(['business_id' => $biz->id, 'name' => 'Toyota Innova', 'type' => 'suv', 'service_mode' => 'taxi', 'seats' => 7, 'base_fare' => 100, 'fare_per_km' => 15, 'min_km' => 3, 'availability_status' => 'available', 'is_active' => true]);
Vehicle::create(['business_id' => $biz->id, 'name' => 'Maruti Van', 'type' => 'van', 'service_mode' => 'shared', 'seats' => 12, 'base_fare' => 50, 'fare_per_km' => 8, 'min_km' => 5, 'availability_status' => 'available', 'is_active' => true]);
Vehicle::create(['business_id' => $biz->id, 'name' => 'Tata Truck', 'type' => 'truck', 'service_mode' => 'goods', 'seats' => 2, 'capacity_value' => 5, 'capacity_unit' => 'tons', 'base_fare' => 500, 'fare_per_km' => 25, 'min_km' => 10, 'availability_status' => 'available', 'is_active' => true]);
Vehicle::create(['business_id' => $biz->id, 'name' => 'Royal Enfield', 'type' => 'bike', 'service_mode' => 'rental', 'seats' => 1, 'base_fare' => 200, 'fare_per_km' => 5, 'min_km' => 1, 'availability_status' => 'available', 'is_active' => true]);
echo "4 vehicles created\n";

echo "\nDONE! Login: vendor@test.com / Test@2026\n";
