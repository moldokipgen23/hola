<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_shows_slim_grouped_nav(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('admin.settings') . '"', false)
            ->assertSee('href="' . route('admin.import') . '"', false)
            ->assertSee('href="' . route('admin.claims') . '"', false)
            ->assertSee('href="' . route('admin.reviews') . '"', false)
            ->assertSee('>Catalog</p>', false)
            ->assertSee('>Operations</p>', false)
            ->assertSee('>People</p>', false)
            ->assertSee('>System</p>', false)
            ->assertSee('>Advanced</summary>', false);
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

    public function test_staff_link_gated_to_super_admin_and_admin_roles(): void
    {
        $staff = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('href="' . route('admin.staff') . '"', false);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('admin.staff') . '"', false);
    }
}
