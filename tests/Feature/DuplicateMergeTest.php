<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\User;
use App\Services\ImportMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateMergeTest extends TestCase
{
    use RefreshDatabase;

    private function batch(): ImportBatch
    {
        return ImportBatch::create([
            'source' => 'google_places',
            'name' => 'Batch',
            'total' => 1,
            'pending' => 1,
            'status' => 'processing',
        ]);
    }

    private function service(): ImportMergeService
    {
        return app(ImportMergeService::class);
    }

    public function test_find_existing_duplicate_matches_by_phone(): void
    {
        $category = Category::create(['name' => 'Retail', 'slug' => 'retail-merge-'.uniqid(), 'is_active' => true]);
        $existing = Business::create([
            'name' => 'Lamka General Store',
            'slug' => 'lamka-general-store-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Main Bazaar, Lamka',
            'phone' => '9000100100',
            'is_active' => true,
        ]);

        $item = ImportItem::create([
            'batch_id' => $this->batch()->id,
            'status' => 'pending',
            'data' => ['name' => 'Lamka General Store', 'address' => 'Main Bazaar, Lamka', 'phone' => '9000100100'],
            'external_id' => 'gplace_merge_'.uniqid(),
        ]);

        $this->assertSame($existing->id, $this->service()->findExistingDuplicate($item)?->id);
    }

    public function test_flag_duplicate_links_item_to_existing_business(): void
    {
        $category = Category::create(['name' => 'Retail', 'slug' => 'retail-flag-'.uniqid(), 'is_active' => true]);
        $existing = Business::create([
            'name' => 'Existing Shop',
            'slug' => 'existing-shop-flag-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Road',
            'phone' => '9000110000',
            'is_active' => true,
        ]);

        $batch = $this->batch();
        $item = ImportItem::create([
            'batch_id' => $batch->id,
            'status' => 'pending',
            'data' => ['name' => 'Existing Shop', 'phone' => '9000110000'],
            'external_id' => 'gplace_flag_'.uniqid(),
        ]);

        $this->service()->flagDuplicate($item, $existing);

        $item->refresh();
        $this->assertSame(ImportItem::STATUS_DUPLICATE, $item->status);
        $this->assertSame($existing->id, $item->duplicate_of);
        $this->assertSame(1, $batch->fresh()->rejected);
        $this->assertSame(0, $batch->fresh()->pending);
    }

    public function test_merge_copies_missing_fields_and_marks_merged(): void
    {
        $category = Category::create(['name' => 'Retail', 'slug' => 'retail-merge2-'.uniqid(), 'is_active' => true]);
        $existing = Business::create([
            'name' => 'Existing Shop',
            'slug' => 'existing-shop-merge-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Road',
            'phone' => '9000120000',
            'description' => 'Original description',
            'is_active' => true,
        ]);

        $batch = $this->batch();
        $item = ImportItem::create([
            'batch_id' => $batch->id,
            'status' => ImportItem::STATUS_DUPLICATE,
            'duplicate_of' => $existing->id,
            'data' => ['name' => 'Existing Shop', 'phone' => '9000121111', 'description' => 'Better description', 'website' => 'https://example.com', 'rating' => 4.5],
            'external_id' => 'gplace_ext_'.uniqid(),
        ]);

        $fieldsCopied = $this->service()->merge($item);

        $existing->refresh();
        $item->refresh();

        $this->assertGreaterThanOrEqual(1, $fieldsCopied);
        $this->assertSame(ImportItem::STATUS_MERGED, $item->status);
        $this->assertSame($existing->id, $item->duplicate_of);
        // Existing description preferred over the import's.
        $this->assertSame('Original description', $existing->description);
        // Missing fields copied over / attached.
        $this->assertSame('https://example.com', $existing->website);
        $this->assertSame(4.5, (float) $existing->average_rating);
        $this->assertSame($item->external_id, $existing->external_id);
    }

    public function test_approve_on_duplicate_flags_for_merge_instead_of_rejecting(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::create(['name' => 'Retail', 'slug' => 'retail-flag2-'.uniqid(), 'is_active' => true]);
        Business::create([
            'name' => 'Existing Shop',
            'slug' => 'existing-shop-flag2-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Road',
            'phone' => '9000130000',
            'is_active' => true,
        ]);

        $item = ImportItem::create([
            'batch_id' => $this->batch()->id,
            'status' => ImportItem::STATUS_REVIEW,
            'data' => ['name' => 'Existing Shop', 'phone' => '9000130000'],
            'external_id' => 'gplace_approve_'.uniqid(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.import.approve', $item->id))
            ->assertSessionHas('error');

        $item->refresh();
        $this->assertSame(ImportItem::STATUS_DUPLICATE, $item->status);
        $this->assertNotNull($item->duplicate_of);
    }

    public function test_admin_can_merge_a_flagged_duplicate(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::create(['name' => 'Retail', 'slug' => 'retail-merge3-'.uniqid(), 'is_active' => true]);
        $existing = Business::create([
            'name' => 'Existing Shop',
            'slug' => 'existing-shop-merge3-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Road',
            'phone' => '9000140000',
            'is_active' => true,
        ]);

        $item = ImportItem::create([
            'batch_id' => $this->batch()->id,
            'status' => ImportItem::STATUS_DUPLICATE,
            'duplicate_of' => $existing->id,
            'data' => ['name' => 'Existing Shop', 'phone' => '9000140000', 'website' => 'https://example.org'],
            'external_id' => 'gplace_mergeweb_'.uniqid(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.import.merge', $item->id))
            ->assertSessionHas('success');

        $item->refresh();
        $this->assertSame(ImportItem::STATUS_MERGED, $item->status);
        $this->assertSame('https://example.org', $existing->fresh()->website);
    }
}
