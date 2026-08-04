<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    private array $getRoutes = [
        'admin.dashboard',
        'admin.businesses',
        'admin.claims',
        'admin.import',
        'admin.category-tree',
        'admin.reviews',
        'admin.orders',
        'admin.products',
        'admin.product-categories',
        'admin.shop-sections',
        'admin.services',
        'admin.bookings',
        'admin.users',
        'admin.vendors',
        'admin.vendors.export',
        'admin.areas',
        'admin.pincodes',
        'admin.vehicle-types',
        'admin.analytics',
        'admin.search-history',
        'admin.reports',
        'admin.feature-flags',
        'admin.settings',
        'admin.capability-templates',
        'admin.transactions',
        'admin.activity-logs',
        'admin.integration-keys',
        'admin.autopilot',
        'admin.agents',
        'admin.staff',
        'admin.area-interests',
    ];

    public function test_every_admin_sidebar_page_loads_without_server_error(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        foreach (FeatureFlag::where('key', 'like', 'world.%')->get() as $flag) {
            $flag->update(['is_enabled' => true]);
        }
        LaunchControlService::clearCache();

        $admin = User::factory()->create(['role' => 'super_admin']);

        $failures = [];
        foreach ($this->getRoutes as $route) {
            try {
                $response = $this->actingAs($admin)->get(route($route));
            } catch (\Throwable $e) {
                $failures[] = "$route threw ".get_class($e).': '.$e->getMessage();
                continue;
            }

            if ($response->isServerError()) {
                $failures[] = "$route returned ".$response->status();
            }
        }

        $this->assertSame([], $failures, "Admin pages failing:\n".implode("\n", $failures));
    }
}
