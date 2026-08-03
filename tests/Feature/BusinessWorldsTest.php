<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessClassification;
use App\Models\Category;
use App\Models\User;
use App\Models\World;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessWorldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldSeeder::class);
    }

    public function test_business_resolves_its_world_through_classified_category(): void
    {
        $book = World::where('slug', 'book')->firstOrFail();

        $category = Category::create([
            'name' => 'Turf & Sports',
            'slug' => 'turf-sports',
            'module_type' => 'booking',
            'world_id' => $book->id,
            'level' => 1,
            'is_active' => true,
        ]);

        $owner = User::factory()->create(['role' => 'owner']);
        $business = Business::create([
            'created_by' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Lamka Turf',
            'slug' => 'lamka-turf',
            'address' => 'Test',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        BusinessClassification::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'is_primary' => true,
            'is_active' => true,
            'source' => 'test',
        ]);

        // Business::worlds() must resolve the Book world (previously returned nothing
        // because categories had a null world_id).
        $this->assertTrue($business->hasWorld('book'));
        $this->assertFalse($business->hasWorld('shop'));
        $this->assertEqualsCanonicalizing(
            ['book'],
            $business->worlds()->pluck('worlds.slug')->all(),
        );
    }
}
