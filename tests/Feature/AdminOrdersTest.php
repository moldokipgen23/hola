<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\Order;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
{
    use RefreshDatabase;

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

    private function business(string $name, array $modules, ?int $categoryId = null): Business
    {
        $categoryId ??= $this->category($name.' Cat', 'ordering')->id;

        return Business::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'category_id' => $categoryId,
            'address' => 'Test street',
            'enabled_modules' => $modules,
        ]);
    }

    private function order(Business $business, string $number): Order
    {
        return Order::create([
            'business_id' => $business->id,
            'order_number' => $number,
            'customer_name' => 'Test Customer',
            'customer_phone' => '9876543210',
            'total' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    private function booking(Business $business, string $customerName): Booking
    {
        return Booking::create([
            'business_id' => $business->id,
            'customer_name' => $customerName,
            'customer_phone' => '9876543210',
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'total_price' => 500,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    private function trip(Business $business, string $customerName): Trip
    {
        $vehicleType = VehicleType::first()
            ?? VehicleType::create(['name' => 'Car', 'slug' => 'car', 'is_active' => true]);
        $vehicle = Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Sedan',
            'type' => $vehicleType->slug,
            'service_mode' => 'taxi',
            'seats' => 4,
        ]);

        return Trip::create([
            'business_id' => $business->id,
            'vehicle_id' => $vehicle->id,
            'customer_name' => $customerName,
            'customer_phone' => '9876543210',
            'pickup_location' => 'A',
            'drop_location' => 'B',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    private function enableAllWorlds(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        $this->seed(WorldSeeder::class);
        foreach (FeatureFlag::where('key', 'like', 'world.%')->get() as $flag) {
            $flag->update(['is_enabled' => true]);
        }
        LaunchControlService::clearCache();
    }

    public function test_each_department_sees_its_own_records(): void
    {
        $this->enableAllWorlds();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $shop = $this->business('Grocery Hub', ['catalog' => true, 'orders' => true]);
        $booking = $this->business('Salon Appoint', ['bookings' => true]);
        $taxi = $this->business('City Cabs', ['transport' => true]);

        $shopOrder = $this->order($shop, 'SHOP-001');
        $bookingRecord = $this->booking($booking, 'Booking Customer');
        $taxiTrip = $this->trip($taxi, 'Taxi Customer');

        // Shopping orders page only shows the `orders` table (shopping orders).
        $this->actingAs($admin)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('SHOP-001')
            ->assertDontSee('Booking Customer')
            ->assertDontSee('Taxi Customer');

        // Bookings page shows the `bookings` table.
        $this->actingAs($admin)
            ->get(route('admin.bookings'))
            ->assertOk()
            ->assertSee('Booking Customer')
            ->assertDontSee('SHOP-001');

        // Trips page shows the `trips` table.
        $this->actingAs($admin)
            ->get(route('admin.trips'))
            ->assertOk()
            ->assertSee('Taxi Customer')
            ->assertDontSee('SHOP-001');

        $this->assertNotNull($shopOrder->id);
        $this->assertNotNull($bookingRecord->id);
        $this->assertNotNull($taxiTrip->id);
    }

    public function test_universal_orders_page_shows_all_modules(): void
    {
        $this->enableAllWorlds();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $groceryType = Category::where('slug', 'grocery')->firstOrFail();
        $shop = $this->business('Grocery Hub', ['catalog' => true, 'orders' => true], $groceryType->id);
        $booking = $this->business('Salon Appoint', ['bookings' => true]);
        $taxi = $this->business('City Cabs', ['transport' => true]);

        $this->order($shop, 'SHOP-001');
        $this->booking($booking, 'Booking Customer');
        $this->trip($taxi, 'Taxi Customer');

        $this->actingAs($admin)
            ->get(route('admin.orders.universal'))
            ->assertOk()
            ->assertSee('SHOP-001')
            ->assertSee('Booking Customer')
            ->assertSee('Taxi Customer')
            ->assertSee('All (3)')
            ->assertSee('Shopping (1)')
            ->assertSee('Booking (1)')
            ->assertSee('Taxi (1)');

        // Shopping module tab shows only orders, with grocery filter working.
        $this->actingAs($admin)
            ->get(route('admin.orders.universal', ['module' => 'shopping']))
            ->assertOk()
            ->assertSee('SHOP-001')
            ->assertDontSee('Booking Customer')
            ->assertDontSee('Taxi Customer')
            ->assertSee('Grocery (1)');

        $this->actingAs($admin)
            ->get(route('admin.orders.universal', ['module' => 'booking']))
            ->assertOk()
            ->assertSee('Booking Customer')
            ->assertDontSee('SHOP-001');

        $this->actingAs($admin)
            ->get(route('admin.orders.universal', ['module' => 'taxi']))
            ->assertOk()
            ->assertSee('Taxi Customer')
            ->assertDontSee('SHOP-001');
    }
}
