<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessClassification;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassificationReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Pincode::create(['pincode' => '795128', 'district' => 'Churachandpur', 'state' => 'Manipur', 'serviceable' => true]);
        $this->world = World::create(['name' => 'Discover', 'slug' => 'discover', 'is_active' => true]);
        $this->category = Category::create([
            'name' => 'General Services',
            'slug' => 'general-services',
            'module_type' => 'directory',
            'world_id' => $this->world->id,
            'level' => 1,
        ]);
    }

    private function createBusiness(array $attributes = []): Business
    {
        return Business::create(array_merge([
            'category_id' => $this->category->id,
            'name' => 'Classification Test Biz',
            'slug' => 'classification-test-biz-'.uniqid(),
            'address' => 'Test Road',
            'pincode' => '795128',
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ], $attributes));
    }

    public function test_admin_created_business_gets_a_primary_classification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.businesses.store'), [
            'name' => 'Admin Made Shop',
            'category_id' => $this->category->id,
            'address' => 'Main Road',
            'pincode' => '795128',
        ])->assertRedirect(route('admin.businesses'));

        $business = Business::where('name', 'Admin Made Shop')->firstOrFail();
        $this->assertTrue($business->hasClassification($this->category->id));
        $this->assertNotNull($business->primaryClassification, 'primary classification must exist');
        $this->assertSame($this->category->id, $business->primaryClassification->category_id);
    }

    public function test_admin_update_moves_the_primary_classification_with_the_business(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = Category::create([
            'name' => 'Other Category',
            'slug' => 'other-category',
            'module_type' => 'directory',
            'world_id' => $this->world->id,
            'level' => 1,
        ]);
        $business = $this->createBusiness();

        $this->actingAs($admin)->put(route('admin.businesses.update', $business), [
            'name' => $business->name,
            'category_id' => $other->id,
            'address' => $business->address,
        ])->assertRedirect(route('admin.businesses'));

        $this->assertSame(
            (int) $other->id,
            (int) $business->fresh()->primaryClassification->category_id,
            'primary classification follows the business category'
        );
    }

    public function test_category_business_count_equals_classification_count(): void
    {
        $this->createBusiness();
        $this->createBusiness(['name' => 'Second Biz', 'slug' => 'second-biz-'.uniqid()]);

        $count = BusinessClassification::where('category_id', $this->category->id)
            ->where('is_active', true)
            ->count();

        $fresh = Category::withCount('businesses')->find($this->category->id);
        $this->assertSame($count, (int) $fresh->businesses_count);
    }

    public function test_legacy_business_without_classification_is_backfilled(): void
    {
        $business = $this->createBusiness();
        BusinessClassification::where('business_id', $business->id)->delete();

        DB::table('migrations')
            ->where('migration', 'like', '%backfill_primary_classifications%')
            ->delete();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_08_05_000002_backfill_primary_classifications.php',
            '--force' => true,
        ]);

        $this->assertTrue($business->fresh()->hasClassification($this->category->id));
        $this->assertTrue($business->fresh()->primaryClassification->is_primary);
    }

    public function test_backfill_is_idempotent(): void
    {
        $this->createBusiness();

        DB::table('migrations')
            ->where('migration', 'like', '%backfill_primary_classifications%')
            ->delete();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_08_05_000002_backfill_primary_classifications.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_08_05_000002_backfill_primary_classifications.php',
            '--force' => true,
        ]);

        $this->assertSame(1, BusinessClassification::where('business_id', $this->category->businesses->first()->id)->count());
    }
}
