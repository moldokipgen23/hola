<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubcategoryConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $world = World::create(['name' => 'Discover', 'slug' => 'discover', 'is_active' => true]);
        $this->world = $world;

        $this->parent = Category::create([
            'name' => 'Test Consultancy',
            'slug' => 'test-consultancy-parent',
            'module_type' => 'directory',
            'world_id' => $world->id,
            'level' => 1,
            'launch_phase' => 'phase1',
            'is_canonical' => true,
        ]);

        $this->legacy = Subcategory::create([
            'category_id' => $this->parent->id,
            'name' => 'Chartered Accountants',
            'slug' => 'test-ca-firms',
            'recommended_modules' => ['catalog' => false, 'bookings' => true],
        ]);
    }

    private function rerunMigration(): void
    {
        DB::table('migrations')
            ->where('migration', 'like', '%migrate_subcategories_to_category_children%')
            ->delete();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_08_05_000001_migrate_subcategories_to_category_children.php',
            '--force' => true,
        ]);
    }

    public function test_subcategory_becomes_a_level_two_category_child(): void
    {
        $this->rerunMigration();

        $child = Category::where('parent_id', $this->parent->id)->where('name', 'Chartered Accountants')->first();
        $this->assertNotNull($child, 'migrated child category must exist under its parent');
        $this->assertSame((int) $this->world->id, (int) $child->world_id, 'child inherits parent world');
        $this->assertSame(2, (int) $child->level);
        $this->assertSame(1, (int) $child->metadata['migrated_from_subcategory']);
        $this->assertSame((int) $this->legacy->id, (int) $child->metadata['legacy_subcategory_id']);
        $this->assertSame(['catalog' => false, 'bookings' => true], $child->metadata['recommended_modules']);
    }

    public function test_business_still_resolves_category_after_repoint(): void
    {
        $business = Business::create([
            'name' => 'ACME CA Firm',
            'slug' => 'acme-ca-firm',
            'category_id' => $this->parent->id,
            'subcategory_id' => $this->legacy->id,
            'address' => 'Main Street',
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $this->rerunMigration();

        $child = Category::where('parent_id', $this->parent->id)->where('name', 'Chartered Accountants')->firstOrFail();

        $business->refresh();
        $this->assertSame((int) $child->id, (int) $business->subcategory_id, 'subcategory_id repointed at child category');
        $this->assertSame((int) $this->parent->id, (int) $business->category_id, 'category_id unchanged for already-categorised business');
        $this->assertInstanceOf(Category::class, $business->subcategory);
        $this->assertSame('Chartered Accountants', $business->subcategory->name);
        $this->assertSame(['catalog' => false, 'bookings' => true], $business->subcategory->recommended_modules);
    }

    public function test_migration_is_idempotent(): void
    {
        $this->rerunMigration();
        $this->rerunMigration();

        $this->assertSame(
            1,
            Category::where('parent_id', $this->parent->id)->where('name', 'Chartered Accountants')->count(),
            're-running the migration must not duplicate children'
        );
    }

    public function test_import_subcategory_resolution_targets_migrated_children(): void
    {
        $this->rerunMigration();
        $child = Category::where('parent_id', $this->parent->id)->where('name', 'Chartered Accountants')->firstOrFail();

        $taxonomy = resolveApprovedImportTaxonomy([
            'category_id' => $this->parent->id,
            'subcategory_id' => $this->legacy->id,
        ]);

        $this->assertNotNull($taxonomy);
        $this->assertSame((int) $this->parent->id, (int) $taxonomy['category_id']);
        $this->assertSame((int) $child->id, (int) $taxonomy['subcategory_id'], 'legacy subcategory id resolves through metadata');
    }

    public function test_admin_subcategory_screens_are_hidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.subcategories'))
            ->assertRedirect(route('admin.categories'));

        $this->actingAs($admin)
            ->post(route('admin.subcategories.store'), ['category_id' => $this->parent->id, 'name' => 'New Legacy'])
            ->assertRedirect(route('admin.categories'));

        $this->assertDatabaseMissing('subcategories', ['name' => 'New Legacy']);
    }
}
