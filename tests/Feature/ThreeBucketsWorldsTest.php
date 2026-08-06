<?php

namespace Tests\Feature;

use App\Models\World;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreeBucketsWorldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldSeeder::class);
    }

    public function test_worlds_are_reseeded_to_four_active_buckets(): void
    {
        $active = World::active()->orderBy('sort_order')->pluck('slug')->all();
        $this->assertEqualsCanonicalizing(['shop', 'ride', 'book', 'discover'], $active);
        $this->assertSame('Shopping', World::where('slug', 'shop')->value('name'));
        $this->assertSame('Ride', World::where('slug', 'ride')->value('name'));
        $this->assertSame('Booking', World::where('slug', 'book')->value('name'));
        $this->assertSame('Directory', World::where('slug', 'discover')->value('name'));
    }

    public function test_nav_config_no_longer_hardcodes_sub_tabs(): void
    {
        foreach (['shop', 'ride', 'book', 'discover'] as $slug) {
            $nav = World::where('slug', $slug)->value('nav_config');
            $this->assertIsArray($nav);
            $this->assertArrayNotHasKey('sub_tabs', $nav ?? []);
        }
    }

    public function test_api_worlds_returns_four_buckets(): void
    {
        $this->getJson('/api/worlds')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonFragment(['slug' => 'ride'])
            ->assertJsonFragment(['slug' => 'shop'])
            ->assertJsonFragment(['slug' => 'book'])
            ->assertJsonFragment(['slug' => 'discover']);
    }

    public function test_world_categories_expose_seeded_level_one_sub_tabs(): void
    {
        $this->getJson('/api/worlds/book/categories')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'taxi'])
            ->assertJsonFragment(['slug' => 'hotel'])
            ->assertJsonFragment(['slug' => 'turf']);

        $this->getJson('/api/worlds/shop/categories')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'food'])
            ->assertJsonFragment(['slug' => 'grocery']);

        $this->getJson('/api/worlds/discover/categories')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'businesses'])
            ->assertJsonFragment(['slug' => 'professionals']);
    }

    public function test_ride_world_is_available_to_the_public_api(): void
    {
        $this->getJson('/api/worlds/ride')->assertOk();
    }
}
