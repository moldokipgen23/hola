<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\World;
use Database\Seeders\LaunchPhase1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchPhase1Test extends TestCase
{
    use RefreshDatabase;

    public function test_phase1_seeder_enables_only_directory_and_turf_flags(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        $enabled = FeatureFlag::where('is_enabled', true)->pluck('key')->sort()->values();
        $this->assertSame([
            'experience.directory',
            'experience.turf',
            'module.bookings',
            'module.turf',
            'world.book',
            'world.discover',
        ], $enabled->all());

        $this->assertFalse(FeatureFlag::where('key', 'world.shop')->firstOrFail()->is_enabled);
        $this->assertFalse(FeatureFlag::where('key', 'world.ride')->firstOrFail()->is_enabled);
        $this->assertFalse(FeatureFlag::where('key', 'payments.online')->firstOrFail()->is_enabled);
    }

    public function test_public_config_lists_only_discover_and_book_tabs_after_phase1(): void
    {
        $this->seed(LaunchPhase1Seeder::class);

        $this->getJson('/api/platform/features')
            ->assertOk()
            ->assertJsonPath('data.worlds.shop', false)
            ->assertJsonPath('data.worlds.ride', false)
            ->assertJsonPath('data.worlds.book', true)
            ->assertJsonPath('data.worlds.discover', true)
            ->assertJsonPath('data.modules.catalog', false)
            ->assertJsonPath('data.modules.bookings', true)
            ->assertJsonPath('data.modules.turf', true)
            ->assertJsonPath('data.experiences.directory', true)
            ->assertJsonPath('data.experiences.turf', true)
            ->assertJsonPath('data.experiences.retail', false)
            ->assertJsonPath('data.payments.online', false)
            ->assertJsonPath('data.enabled_tabs', ['book', 'discover']);
    }

    public function test_world_api_exposes_only_directory_and_turf_after_phase1(): void
    {
        foreach (['shop', 'ride', 'book', 'discover'] as $slug) {
            World::create(['name' => ucfirst($slug), 'slug' => $slug, 'is_active' => true]);
        }
        $this->seed(LaunchPhase1Seeder::class);

        $this->getJson('/api/worlds')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'shop'])
            ->assertJsonMissing(['slug' => 'ride'])
            ->assertJsonFragment(['slug' => 'book'])
            ->assertJsonFragment(['slug' => 'discover']);

        $this->getJson('/api/worlds/shop')->assertNotFound();
    }
}
