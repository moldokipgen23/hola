<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_analytics_page_displays_revenue_and_breakdown(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        [$shop, $bookingBiz] = $this->fixtures();

        Order::create([
            'business_id' => $shop->id,
            'order_number' => 'ORD-1',
            'customer_name' => 'A',
            'customer_phone' => '9876543210',
            'total' => 200,
            'status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        Booking::create([
            'business_id' => $bookingBiz->id,
            'customer_name' => 'B',
            'customer_phone' => '9876543210',
            'booking_date' => now()->toDateString(),
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
            'duration_minutes' => 60,
            'total_price' => 150,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.booking-analytics'))
            ->assertOk()
            ->assertSee('Booking Analytics')
            ->assertSee('350.00')
            ->assertSee('Grocery Hub')
            ->assertSee('Salon Appoint');
    }

    public function test_booking_analytics_csv_export(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        [$shop, $bookingBiz] = $this->fixtures();

        Booking::create([
            'business_id' => $bookingBiz->id,
            'customer_name' => 'B',
            'customer_phone' => '9876543210',
            'booking_date' => now()->toDateString(),
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
            'duration_minutes' => 60,
            'total_price' => 150,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.booking-analytics', ['format' => 'csv']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
            ->assertSee('Business')
            ->assertSee('Salon Appoint');
    }

    private function fixtures(): array
    {
        $shopCat = Category::create(['name' => 'Grocery', 'slug' => 'grocery-analytics-'.uniqid(), 'module_type' => 'ordering']);
        $shop = Business::create([
            'name' => 'Grocery Hub',
            'slug' => 'grocery-hub-analytics-'.uniqid(),
            'category_id' => $shopCat->id,
            'address' => 'x',
            'enabled_modules' => ['catalog' => true, 'orders' => true],
        ]);

        $bookingCat = Category::create(['name' => 'Salon', 'slug' => 'salon-analytics-'.uniqid(), 'module_type' => 'booking']);
        $bookingBiz = Business::create([
            'name' => 'Salon Appoint',
            'slug' => 'salon-appoint-analytics-'.uniqid(),
            'category_id' => $bookingCat->id,
            'address' => 'y',
            'enabled_modules' => ['bookings' => true],
        ]);

        return [$shop, $bookingBiz];
    }
}
