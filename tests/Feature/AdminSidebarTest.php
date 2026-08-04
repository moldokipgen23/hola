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

    public function test_admin_sidebar_shows_grouped_department_nav(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('admin.settings') . '"', false)
            ->assertSee('href="' . route('admin.import') . '"', false)
            ->assertSee('href="' . route('admin.claims') . '"', false)
            ->assertSee('href="' . route('admin.reviews') . '"', false)
            ->assertSee('href="' . route('admin.vendors') . '"', false)
            ->assertSee('>Overview</p>', false)
            ->assertSee('>Directory</p>', false)
            ->assertSee('>Users</p>', false)
            ->assertSee('>Fulfillment</p>', false)
            ->assertSee('>Analytics</p>', false)
            ->assertSee('>Content</p>', false)
            ->assertSee('>Settings</p>', false)
            ->assertSee('>System</p>', false)
            ->assertSee('>AI Agents</p>', false);
    }

    public function test_admin_sidebar_hides_shop_department_in_phase_one_launch(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('>Catalog</p>', false)
            ->assertDontSee('>Orders</p>', false)
            ->assertDontSee('href="' . route('admin.products') . '"', false)
            ->assertDontSee('href="' . route('admin.orders') . '"', false)
            ->assertDontSee('>Shopping</span>', false)
            ->assertDontSee('>Taxi / Transport</span>', false);
    }

    public function test_admin_sidebar_shows_shop_and_book_departments_when_worlds_enabled(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        FeatureFlag::where('key', 'world.shop')->firstOrFail()->update(['is_enabled' => true]);
        FeatureFlag::where('key', 'world.book')->firstOrFail()->update(['is_enabled' => true]);
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Catalog</p>', false)
            ->assertSee('>Orders</p>', false)
            ->assertSee('href="' . route('admin.products') . '"', false)
            ->assertSee('href="' . route('admin.orders') . '"', false)
            ->assertSee('href="' . route('admin.bookings') . '"', false)
            ->assertSee('href="' . route('admin.services') . '"', false)
            ->assertSee('href="' . route('admin.businesses') . '"', false);
    }

    public function test_admin_sidebar_hides_book_department_when_book_world_disabled(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        FeatureFlag::where('key', 'world.book')->firstOrFail()->update(['is_enabled' => false]);
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('>Catalog</p>', false)
            ->assertDontSee('href="' . route('admin.bookings') . '"', false)
            ->assertDontSee('href="' . route('admin.services') . '"', false)
            ->assertSee('href="' . route('admin.businesses') . '"', false);
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
            ->assertDontSee('href="' . route('admin.staff') . '"', false)
            ->assertDontSee('href="' . route('admin.integration-keys') . '"', false)
            ->assertDontSee('href="' . route('admin.activity-logs') . '"', false)
            ->assertDontSee('href="' . route('admin.agents') . '"', false)
            ->assertDontSee('>AI Agents</p>', false);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('admin.staff') . '"', false)
            ->assertSee('href="' . route('admin.integration-keys') . '"', false)
            ->assertSee('href="' . route('admin.activity-logs') . '"', false)
            ->assertSee('href="' . route('admin.autopilot') . '"', false)
            ->assertSee('href="' . route('admin.agents') . '"', false)
            ->assertSee('>Users</p>', false)
            ->assertSee('>AI Agents</p>', false);
    }
}
