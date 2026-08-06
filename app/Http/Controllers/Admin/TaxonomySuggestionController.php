<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SourceTaxonomyMapping;
use App\Models\Subcategory;
use App\Models\TaxonomySuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TaxonomySuggestionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status', 'pending')->toString();
        $status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'pending';

        $suggestions = TaxonomySuggestion::query()
            ->with(['agent:id,name', 'importItem:id,data', 'business:id,name'])
            ->where('status', $status)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $categories = Category::active()
            ->where('is_canonical', true)
            ->with(['subcategories' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('admin.taxonomy.suggestions', compact('suggestions', 'categories', 'status'));
    }

    public function resolve(Request $request, TaxonomySuggestion $suggestion): RedirectResponse
    {
        abort_unless($suggestion->status === 'pending', 422, 'This suggestion has already been reviewed.');

        $validated = $request->validate([
            'action' => 'required|in:map_existing,create_category,reject',
            'category_id' => 'nullable|required_if:action,map_existing|exists:categories,id',
            'subcategory_id' => 'nullable|exists:subcategories,id',
            'category_name' => 'nullable|required_if:action,create_category|string|max:255',
            'recommended_modules' => 'nullable|array',
            'recommended_modules.*' => 'in:catalog,orders,bookings,inventory,transport,turf',
            'review_notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($suggestion, $validated) {
            if ($validated['action'] === 'reject') {
                $suggestion->update([
                    'status' => 'rejected',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'review_notes' => $validated['review_notes'] ?? null,
                ]);

                return;
            }

            $category = $validated['action'] === 'create_category'
                ? $this->createCategoryFromSuggestion($validated)
                : Category::active()->where('is_canonical', true)->findOrFail($validated['category_id']);

            if (! $category->is_canonical) {
                $category->update(['is_canonical' => true]);
            }

            $subcategoryId = $validated['subcategory_id'] ?? null;
            if ($subcategoryId) {
                Subcategory::where('category_id', $category->id)->findOrFail($subcategoryId);
            }

            if ($suggestion->source_provider && $suggestion->source_type) {
                SourceTaxonomyMapping::updateOrCreate(
                    [
                        'provider' => $suggestion->source_provider,
                        'source_type' => $suggestion->source_type,
                    ],
                    [
                        'category_id' => $category->id,
                        'subcategory_id' => $subcategoryId,
                        'recommended_modules' => $validated['recommended_modules'] ?? [],
                        'confidence' => 1,
                        'is_active' => true,
                    ]
                );
            }

            if ($suggestion->suggestion_type === 'business_reclassification' && $suggestion->business) {
                $suggestion->business->syncPrimaryClassification($category->id, 'taxonomy_review');
            }

            if ($suggestion->importItem) {
                $data = $suggestion->importItem->data;
                $data['category'] = $category->name;
                $data['category_id'] = $category->id;
                $suggestion->importItem->update([
                    'data' => $data,
                    'confidence' => min(($suggestion->importItem->confidence ?? 0.5) + 0.2, 1.0),
                ]);
            }

            $suggestion->update([
                'status' => 'approved',
                'suggested_parent_id' => $category->id,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
            ]);
        });

        return back()->with('success', 'Taxonomy suggestion reviewed.');
    }

    /**
     * Create a canonical category for an approved suggestion. Runs applyTaxonomy
     * so world_id/level are derived from module_type (world browsing depends on it).
     */
    private function createCategoryFromSuggestion(array $validated): Category
    {
        $name = trim((string) $validated['category_name']);

        $existing = Category::where('slug', Str::slug($name))->first();
        if ($existing) {
            return $existing;
        }

        $data = [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => '📂',
            'module_type' => 'directory',
            'is_active' => true,
            'is_canonical' => true,
        ];
        Category::applyTaxonomy($data);

        return Category::create($data);
    }
}
