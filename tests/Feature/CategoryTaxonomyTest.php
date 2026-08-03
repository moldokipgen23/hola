<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\World;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldSeeder::class);
    }

    public function test_new_root_category_derives_single_world_and_level_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bookWorld = World::where('slug', 'book')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Turf & Sports',
                'module_type' => 'booking',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.categories'));

        $category = Category::where('slug', 'turf-sports')->firstOrFail();
        $this->assertSame($bookWorld->id, $category->world_id, 'root category must not be orphaned from its world');
        $this->assertNull($category->parent_id);
        $this->assertSame(1, (int) $category->level);
    }

    public function test_child_category_inherits_parent_world_and_deeper_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bookWorld = World::where('slug', 'book')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Turf & Sports',
            'module_type' => 'booking',
            'is_active' => 1,
        ]);
        $parent = Category::where('slug', 'turf-sports')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Football Turf',
            'module_type' => 'booking',
            'parent_id' => $parent->id,
            'is_active' => 1,
        ])->assertRedirect(route('admin.categories'));

        $child = Category::where('slug', 'football-turf')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame($bookWorld->id, $child->world_id, 'child inherits parent world');
        $this->assertSame(2, (int) $child->level);
    }

    public function test_legacy_both_bucket_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Confusing Category',
                'module_type' => 'both',
            ])
            ->assertSessionHasErrors('module_type');

        $this->assertDatabaseMissing('categories', ['slug' => 'confusing-category']);
    }
}
