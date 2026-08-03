<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\Category;
use App\Models\SourceTaxonomyMapping;
use App\Models\Subcategory;
use App\Models\TaxonomySuggestion;
use App\Models\User;
use App\Services\TaxonomyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_types_resolve_to_admin_controlled_taxonomy(): void
    {
        $category = Category::where('slug', 'transport')->firstOrFail();
        $subcategory = Subcategory::where('category_id', $category->id)
            ->where('slug', 'taxi-services')
            ->firstOrFail();

        SourceTaxonomyMapping::updateOrCreate(
            ['provider' => 'google_places', 'source_type' => 'taxi_stand'],
            [
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
                'recommended_modules' => ['transport'],
                'confidence' => 1,
                'is_active' => true,
            ]
        );

        $resolved = app(TaxonomyService::class)
            ->resolveProviderTypes('google_places', ['point_of_interest', 'taxi_stand']);

        $this->assertSame('transport', $resolved['category_slug']);
        $this->assertSame('taxi-services', $resolved['subcategory_slug']);
        $this->assertSame(['transport'], $resolved['recommended_modules']);
    }

    public function test_unknown_import_category_does_not_create_taxonomy(): void
    {
        $categoriesBefore = Category::count();
        $categoryMap = Category::pluck('id', 'name')->all();

        $resolvedId = matchImportCategory('Unmapped Experimental Business', $categoryMap);

        $this->assertNotNull(Category::find($resolvedId));
        $this->assertSame($categoriesBefore, Category::count());
    }

    public function test_repeated_unknown_source_creates_one_review_suggestion(): void
    {
        $agent = AiAgent::create([
            'name' => 'Taxonomy Scout',
            'role' => 'Taxonomy',
            'provider' => 'deepseek',
            'model' => 'deepseek-chat',
            'skills' => ['auto_categorize'],
            'status' => 'active',
        ]);

        $service = app(TaxonomyService::class);
        $service->suggestUnknown('Community Hall', $agent, sourceProvider: 'google_places', sourceType: 'community_center');
        $service->suggestUnknown('Community Hall', $agent, sourceProvider: 'google_places', sourceType: 'community_center');

        $this->assertSame(1, TaxonomySuggestion::count());
        $this->assertDatabaseHas('taxonomy_suggestions', [
            'suggested_name' => 'Community Hall',
            'status' => 'pending',
        ]);
    }

    public function test_subcategory_matching_uses_category_slug_instead_of_a_fixed_id(): void
    {
        $healthcare = Category::where('slug', 'healthcare')->firstOrFail();
        $pharmacy = Subcategory::where('category_id', $healthcare->id)
            ->where('slug', 'pharmacies')
            ->firstOrFail();

        $this->assertSame(
            $pharmacy->id,
            matchImportSubcategory('pharmacy', 'Community Medical Store', $healthcare->id)
        );
    }

    public function test_admin_can_approve_a_provider_mapping_without_automatic_taxonomy_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::where('slug', 'government-public-services')->firstOrFail();
        $suggestion = TaxonomySuggestion::create([
            'suggestion_type' => 'category',
            'suggested_name' => 'Courthouse',
            'source_provider' => 'google_places',
            'source_type' => 'courthouse',
            'status' => 'pending',
        ]);

        $categoriesBefore = Category::count();

        $this->actingAs($admin)->patch(route('admin.taxonomy.suggestions.resolve', $suggestion), [
            'action' => 'map_existing',
            'category_id' => $category->id,
            'review_notes' => 'Public-information listing.',
        ])->assertRedirect();

        $this->assertSame($categoriesBefore, Category::count());
        $this->assertDatabaseHas('source_taxonomy_mappings', [
            'provider' => 'google_places',
            'source_type' => 'courthouse',
            'category_id' => $category->id,
        ]);
        $this->assertDatabaseHas('taxonomy_suggestions', [
            'id' => $suggestion->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }
}
