<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrdersScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LaunchPhase1Seeder::class);
        $this->seed(WorldSeeder::class);

        foreach (FeatureFlag::where('key', 'like', 'world.%')->get() as $flag) {
            $flag->update(['is_enabled' => true]);
        }
        LaunchControlService::clearCache();
    }

    private function staff(string $department, string $role = 'manager'): User
    {
        return User::factory()->create(['role' => $role, 'department' => $department]);
    }

    public function test_department_staff_only_see_their_own_records_pages(): void
    {
        $shoppingStaff = $this->staff('shopping');
        $this->actingAs($shoppingStaff)->get(route('admin.orders'))->assertOk();
        $this->actingAs($shoppingStaff)->get(route('admin.bookings'))->assertForbidden();
        $this->actingAs($shoppingStaff)->get(route('admin.trips'))->assertForbidden();
        $this->actingAs($shoppingStaff)->get(route('admin.orders.universal'))->assertForbidden();

        $bookingStaff = $this->staff('booking');
        $this->actingAs($bookingStaff)->get(route('admin.bookings'))->assertOk();
        $this->actingAs($bookingStaff)->get(route('admin.orders'))->assertForbidden();
        $this->actingAs($bookingStaff)->get(route('admin.trips'))->assertForbidden();
        $this->actingAs($bookingStaff)->get(route('admin.orders.universal'))->assertForbidden();

        $taxiStaff = $this->staff('taxi');
        $this->actingAs($taxiStaff)->get(route('admin.trips'))->assertOk();
        $this->actingAs($taxiStaff)->get(route('admin.orders'))->assertForbidden();
        $this->actingAs($taxiStaff)->get(route('admin.bookings'))->assertForbidden();
        $this->actingAs($taxiStaff)->get(route('admin.orders.universal'))->assertForbidden();
    }

    public function test_full_access_staff_sees_universal_orders(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.orders.universal'))->assertOk();
    }
}
