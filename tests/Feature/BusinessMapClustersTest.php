<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Services\LaunchControlService;
use Database\Seeders\LaunchPhase1Seeder;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMapClustersTest extends TestCase
{
    use RefreshDatabase;

    public function test_clusters_group_nearby_businesses_at_low_zoom(): void
    {
        $this->enableDirectory();

        // Two businesses within the same ~1.2km cell at low zoom.
        $this->geoBusiness('Cafe One', 24.8070, 93.9270);
        $this->geoBusiness('Cafe Two', 24.8080, 93.9280);
        // One far away.
        $this->geoBusiness('Far Shop', 24.1000, 93.1000);

        $response = $this->getJson('/api/businesses/clusters?latitude=24.8075&longitude=93.9275&zoom=7');
        $response->assertOk()->assertJsonCount(2, 'cells');

        $cluster = collect($response->json('cells'))->firstWhere('is_cluster', true);
        $this->assertNotNull($cluster);
        $this->assertSame(2, $cluster['count']);
        $this->assertTrue(isset($cluster['representative']['name']));
        $this->assertSame(3, $response->json('total'));
    }

    public function test_high_zoom_resolves_each_business_to_its_own_point(): void
    {
        $this->enableDirectory();

        $this->geoBusiness('Cafe One', 24.8070, 93.9270);
        $this->geoBusiness('Cafe Two', 24.8080, 93.9280);

        $response = $this->getJson('/api/businesses/clusters?latitude=24.8075&longitude=93.9275&zoom=16');
        $response->assertOk();

        $this->assertSame(2, $response->json('total'));
        $this->assertTrue(collect($response->json('cells'))->every(fn ($c) => $c['count'] === 1));
    }

    public function test_viewport_bounds_filter_businesses(): void
    {
        $this->enableDirectory();

        $this->geoBusiness('In View', 24.8070, 93.9270);
        $this->geoBusiness('Outside', 24.1000, 93.1000);

        $response = $this->getJson('/api/businesses/clusters?zoom=8&south=24.80&west=93.90&north=24.82&east=93.95');
        $response->assertOk()->assertJsonPath('total', 1);
        $this->assertSame('In View', $response->json('cells.0.representative.name'));
    }

    public function test_clusters_exclude_businesses_without_coordinates(): void
    {
        $this->enableDirectory();

        $this->geoBusiness('With Coords', 24.8070, 93.9270);

        $cat = Category::create(['name' => 'Shop', 'slug' => 'shop-cluster-'.uniqid(), 'module_type' => 'directory']);
        Business::create([
            'name' => 'No Coords',
            'slug' => 'no-coords-'.uniqid(),
            'category_id' => $cat->id,
            'address' => 'x',
            'enabled_modules' => ['directory' => true],
        ]);

        $response = $this->getJson('/api/businesses/clusters?latitude=24.807&longitude=93.927&zoom=8');
        $response->assertOk()->assertJsonPath('total', 1);
        $this->assertSame('With Coords', $response->json('cells.0.representative.name'));
    }

    private function geoBusiness(string $name, float $lat, float $lng): Business
    {
        $cat = Category::create(['name' => 'Cafe', 'slug' => 'cafe-cluster-'.uniqid(), 'module_type' => 'directory']);

        return Business::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'category_id' => $cat->id,
            'address' => 'x',
            'latitude' => $lat,
            'longitude' => $lng,
            'enabled_modules' => ['directory' => true],
        ]);
    }

    private function enableDirectory(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        $this->seed(WorldSeeder::class);
        foreach (FeatureFlag::where('key', 'like', 'world.%')->get() as $flag) {
            $flag->update(['is_enabled' => true]);
        }
        LaunchControlService::clearCache();
    }
}
