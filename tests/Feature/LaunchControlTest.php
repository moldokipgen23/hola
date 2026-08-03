<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Business;
use App\Models\Category;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_config_exposes_server_authoritative_launch_state(): void
    {
        $this->getJson('/api/platform/features')
            ->assertOk()
            ->assertJsonPath('data.worlds.shop', true)
            ->assertJsonPath('data.modules.orders', true)
            ->assertJsonPath('data.experiences.taxi', true)
            ->assertJsonPath('data.payments.online', false);
    }

    public function test_disabling_world_hides_it_from_world_api_and_config(): void
    {
        World::create(['name' => 'Shop', 'slug' => 'shop', 'is_active' => true]);
        World::create(['name' => 'Discover', 'slug' => 'discover', 'is_active' => true]);
        FeatureFlag::where('key', 'world.shop')->firstOrFail()->update(['is_enabled' => false]);

        $this->getJson('/api/platform/features')
            ->assertJsonPath('data.worlds.shop', false);

        $this->getJson('/api/worlds')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'shop'])
            ->assertJsonFragment(['slug' => 'discover']);

        $this->getJson('/api/worlds/shop')->assertNotFound();
    }

    public function test_disabling_module_blocks_public_endpoint(): void
    {
        FeatureFlag::where('key', 'module.orders')->firstOrFail()->update(['is_enabled' => false]);

        $this->postJson('/api/businesses/example/orders', [])
            ->assertNotFound()
            ->assertJsonPath('code', 'feature_unavailable')
            ->assertJsonPath('feature', 'module.orders');
    }

    public function test_disabling_dependency_disables_related_experience(): void
    {
        FeatureFlag::where('key', 'module.transport')->firstOrFail()->update(['is_enabled' => false]);

        $this->getJson('/api/platform/features')
            ->assertJsonPath('data.modules.transport', false)
            ->assertJsonPath('data.experiences.taxi', false)
            ->assertJsonPath('data.experiences.shared_transport', false);
    }

    public function test_disabled_module_is_removed_from_business_capabilities(): void
    {
        $category = Category::firstOrFail();
        $business = Business::create([
            'category_id' => $category->id,
            'name' => 'Launch Test Shop',
            'slug' => 'launch-test-shop',
            'address' => 'Test address',
            'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => ['catalog' => true, 'orders' => true],
            'enabled_experiences' => ['retail', 'directory'],
            'primary_experience' => 'retail',
        ]);
        FeatureFlag::where('key', 'module.orders')->firstOrFail()->update(['is_enabled' => false]);

        $this->getJson("/api/businesses/{$business->slug}")
            ->assertOk()
            ->assertJsonPath('business.capabilities.catalog', true)
            ->assertJsonPath('business.capabilities.orders', false);
    }
}
