<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDepartmentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LaunchPhase1Seeder::class);
        foreach (FeatureFlag::where('key', 'like', 'world.%')->get() as $flag) {
            $flag->update(['is_enabled' => true]);
        }
        LaunchControlService::clearCache();
    }

    private function staff(string $department, string $role = 'moderator'): User
    {
        return User::factory()->create(['role' => $role, 'department' => $department]);
    }

    public function test_shopping_staff_menu_and_url_scope(): void
    {
        $user = $this->staff('shopping');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Shopping</span>', false)
            ->assertSee('>Users</span>', false)
            ->assertSee('>Analytics</span>', false)
            ->assertDontSee('>Directory</span>', false)
            ->assertDontSee('>Booking</span>', false)
            ->assertDontSee('>Taxi / Transport</span>', false)
            ->assertDontSee('>Settings</span>', false)
            ->assertDontSee('>System</span>', false)
            ->assertDontSee('>AI Agents</span>', false)
            ->assertDontSee('href="' . route('admin.staff') . '"', false);

        $this->actingAs($user)->get(route('admin.products'))->assertOk();
        $this->actingAs($user)->get(route('admin.orders'))->assertOk();
        $this->actingAs($user)->get(route('admin.areas'))->assertOk();

        $this->actingAs($user)->get(route('admin.businesses'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.services'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.vehicle-types'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.staff'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.settings'))->assertForbidden();
    }

    public function test_booking_staff_menu_and_url_scope(): void
    {
        $user = $this->staff('booking');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Booking</span>', false)
            ->assertDontSee('>Directory</span>', false)
            ->assertDontSee('>Shopping</span>', false)
            ->assertDontSee('>Taxi / Transport</span>', false);

        $this->actingAs($user)->get(route('admin.services'))->assertOk();
        $this->actingAs($user)->get(route('admin.bookings'))->assertOk();

        $this->actingAs($user)->get(route('admin.orders'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.businesses'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.vehicle-types'))->assertForbidden();
    }

    public function test_taxi_staff_menu_and_url_scope(): void
    {
        $user = $this->staff('taxi');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Taxi / Transport</span>', false)
            ->assertDontSee('>Shopping</span>', false)
            ->assertDontSee('>Booking</span>', false)
            ->assertDontSee('>Directory</span>', false);

        $this->actingAs($user)->get(route('admin.vehicle-types'))->assertOk();
        $this->actingAs($user)->get(route('admin.pincodes'))->assertOk();

        $this->actingAs($user)->get(route('admin.orders'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.services'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.businesses'))->assertForbidden();
    }

    public function test_directory_staff_menu_and_url_scope(): void
    {
        $user = $this->staff('directory');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Directory</span>', false)
            ->assertDontSee('>Shopping</span>', false)
            ->assertDontSee('>Booking</span>', false)
            ->assertDontSee('>Taxi / Transport</span>', false);

        $this->actingAs($user)->get(route('admin.businesses'))->assertOk();
        $this->actingAs($user)->get(route('admin.claims'))->assertOk();
        $this->actingAs($user)->get(route('admin.import'))->assertOk();

        $this->actingAs($user)->get(route('admin.orders'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.bookings'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.vehicle-types'))->assertForbidden();
    }

    public function test_support_staff_sees_only_users_reviews_and_dashboard(): void
    {
        $user = $this->staff('support');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Users</span>', false)
            ->assertSee('>Directory</span>', false)
            ->assertSee('>Reviews</span>', false)
            ->assertDontSee('>Shopping</span>', false)
            ->assertDontSee('>Booking</span>', false)
            ->assertDontSee('>Taxi / Transport</span>', false)
            ->assertDontSee('>Analytics</span>', false)
            ->assertDontSee('>Settings</span>', false)
            ->assertDontSee('>System</span>', false)
            ->assertDontSee('>AI Agents</span>', false);

        $this->actingAs($user)->get(route('admin.users'))->assertOk();
        $this->actingAs($user)->get(route('admin.reviews'))->assertOk();
        $this->actingAs($user)->get(route('admin.vendors'))->assertOk();

        $this->actingAs($user)->get(route('admin.businesses'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.orders'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.analytics'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.staff'))->assertForbidden();
    }

    public function test_full_access_staff_without_department_sees_everything(): void
    {
        $user = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Directory</span>', false)
            ->assertSee('>Shopping</span>', false)
            ->assertSee('>Booking</span>', false)
            ->assertSee('>Taxi / Transport</span>', false)
            ->assertSee('>Users</span>', false)
            ->assertSee('>Analytics</span>', false)
            ->assertSee('>System</span>', false)
            ->assertDontSee('>AI Agents</span>', false);

        foreach (['admin.businesses', 'admin.products', 'admin.orders', 'admin.services', 'admin.bookings', 'admin.vehicle-types', 'admin.pincodes', 'admin.analytics'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_super_admin_staff_member_is_always_full_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'department' => 'shopping']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Directory</span>', false)
            ->assertSee('>Shopping</span>', false)
            ->assertSee('>Booking</span>', false);

        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'department' => 'shopping']))
            ->get(route('admin.businesses'))
            ->assertOk();
    }

    public function test_staff_create_forces_full_access_for_super_admin_and_stores_department(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Shopping Agent',
            'email' => 'shopping-agent@example.com',
            'password' => 'secret123',
            'role' => 'moderator',
            'department' => 'shopping',
            'is_active' => 1,
        ])->assertRedirect(route('admin.staff'));
        $this->assertDatabaseHas('users', ['email' => 'shopping-agent@example.com', 'department' => 'shopping']);

        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Root Agent',
            'email' => 'root-agent@example.com',
            'password' => 'secret123',
            'role' => 'super_admin',
            'department' => 'shopping',
            'is_active' => 1,
        ])->assertRedirect(route('admin.staff'));
        $this->assertDatabaseHas('users', ['email' => 'root-agent@example.com', 'department' => null]);
    }
}
