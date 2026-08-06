<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\ClaimRequest;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_shows_department_nav_in_order(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.settings').'#general"', false)
            ->assertSee('href="'.route('admin.import').'"', false)
            ->assertSee('href="'.route('admin.claims').'"', false)
            ->assertSee('href="'.route('admin.reviews').'"', false)
            ->assertSee('href="'.route('admin.vendors').'"', false)
            ->assertSee('>Overview</span>', false)
            ->assertSee('>Directory &amp; Listings</span>', false)
            ->assertSee('>Sales &amp; Customers</span>', false)
            ->assertSee('>Bookings</span>', false)
            ->assertSee('>Analytics</span>', false)
            ->assertSee('>Settings &amp; Branding</span>', false)
            ->assertSee('>System</span>', false)
            ->assertSee('>AI &amp; Automation</span>', false)
            ->assertSeeInOrder([
                '>Overview</span>',
                '>Directory &amp; Listings</span>',
                '>Sales &amp; Customers</span>',
                '>Bookings</span>',
                '>Analytics</span>',
                '>Settings &amp; Branding</span>',
                '>System</span>',
                '>AI &amp; Automation</span>',
            ], false);
    }

    public function test_admin_sidebar_hides_shop_and_taxi_departments_in_phase_one_launch(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('>Commerce (Shopping)</span>', false)
            ->assertDontSee('>Transport</span>', false)
            ->assertDontSee('href="'.route('admin.products').'"', false)
            ->assertDontSee('href="'.route('admin.orders').'"', false)
            ->assertDontSee('href="'.route('admin.vehicle-types').'"', false)
            ->assertSee('href="'.route('admin.businesses').'"', false);
    }

    public function test_admin_sidebar_shows_all_departments_when_worlds_enabled(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        foreach (FeatureFlag::where('key', 'like', 'world.%')->get() as $flag) {
            $flag->update(['is_enabled' => true]);
        }
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Commerce (Shopping)</span>', false)
            ->assertSee('>Bookings</span>', false)
            ->assertSee('>Transport</span>', false)
            ->assertSee('href="'.route('admin.products').'"', false)
            ->assertSee('href="'.route('admin.orders').'"', false)
            ->assertSee('href="'.route('admin.services').'"', false)
            ->assertSee('href="'.route('admin.bookings').'"', false)
            ->assertSee('href="'.route('admin.vehicle-types').'"', false)
            ->assertSee('href="'.route('admin.pincodes').'"', false)
            ->assertSee('href="'.route('admin.businesses').'"', false);
    }

    public function test_admin_sidebar_hides_booking_department_when_book_world_disabled(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        FeatureFlag::where('key', 'world.book')->firstOrFail()->update(['is_enabled' => false]);
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('>Bookings</span>', false)
            ->assertDontSee('href="'.route('admin.bookings').'"', false)
            ->assertDontSee('href="'.route('admin.services').'"', false)
            ->assertSee('href="'.route('admin.businesses').'"', false);
    }

    public function test_admin_sidebar_shows_pending_claim_badge(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $claimant = User::factory()->create();
        $category = Category::create(['name' => 'Sidebar Test Cafe', 'slug' => 'sidebar-test-cafe', 'module_type' => 'ordering']);
        $business = Business::create(['name' => 'Claimed Cafe', 'slug' => 'claimed-cafe', 'category_id' => $category->id, 'address' => 'Test street']);
        ClaimRequest::create([
            'user_id' => $claimant->id,
            'business_id' => $business->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="sidebar-badge">1</span>', false);
    }

    public function test_internal_tools_gated_to_super_admin_and_admin_roles(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        $staff = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('admin.staff').'"', false)
            ->assertDontSee('href="'.route('admin.integration-keys').'"', false)
            ->assertDontSee('href="'.route('admin.activity-logs').'"', false)
            ->assertDontSee('href="'.route('admin.agents').'"', false)
            ->assertDontSee('>AI &amp; Automation</span>', false);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.staff').'"', false)
            ->assertSee('href="'.route('admin.activity-logs').'"', false)
            ->assertSee('href="'.route('admin.autopilot').'"', false)
            ->assertSee('href="'.route('admin.agents').'"', false)
            ->assertSee('>Sales &amp; Customers</span>', false)
            ->assertSee('>AI &amp; Automation</span>', false);
    }
}
