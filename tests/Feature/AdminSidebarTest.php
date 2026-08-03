<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\ClaimRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_shows_grouped_nav_without_advanced_drawer(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('admin.settings') . '"', false)
            ->assertSee('href="' . route('admin.import') . '"', false)
            ->assertSee('href="' . route('admin.claims') . '"', false)
            ->assertSee('href="' . route('admin.reviews') . '"', false)
            ->assertSee('>Overview</p>', false)
            ->assertSee('>Catalog</p>', false)
            ->assertSee('>Operations</p>', false)
            ->assertSee('>People</p>', false)
            ->assertSee('>Content</p>', false)
            ->assertSee('>System</p>', false)
            ->assertSee('>Automation</p>', false)
            ->assertDontSee('>Advanced</summary>', false);
    }

    public function test_admin_sidebar_shows_pending_claim_badge(): void
    {
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

    public function test_admin_sidebar_hides_transactions_and_orders_until_shopping_launches(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('href="' . route('admin.transactions') . '"', false)
            ->assertDontSee('href="' . route('admin.orders') . '"', false)
            ->assertDontSee('href="' . route('admin.products') . '"', false)
            ->assertDontSee('href="' . route('admin.services') . '"', false);
    }

    public function test_internal_tools_gated_to_super_admin_and_admin_roles(): void
    {
        $staff = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('href="' . route('admin.staff') . '"', false)
            ->assertDontSee('href="' . route('admin.integration-keys') . '"', false)
            ->assertDontSee('href="' . route('admin.activity-logs') . '"', false)
            ->assertDontSee('href="' . route('admin.agents') . '"', false)
            ->assertDontSee('>Autopilot</span>', false)
            ->assertDontSee('>Agent Settings</span>', false)
            ->assertDontSee('>Automation</p>', false);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('admin.staff') . '"', false)
            ->assertSee('href="' . route('admin.integration-keys') . '"', false)
            ->assertSee('href="' . route('admin.activity-logs') . '"', false)
            ->assertSee('href="' . route('admin.autopilot') . '"', false)
            ->assertSee('href="' . route('admin.agents') . '"', false)
            ->assertSee('>Automation</p>', false);
    }
}
