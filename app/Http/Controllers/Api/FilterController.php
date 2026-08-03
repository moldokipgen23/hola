<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryFilter;
use Illuminate\Http\Request;

class FilterController extends Controller
{
    public function index(int $category)
    {
        $filters = CategoryFilter::where('category_id', $category)
            ->active()
            ->filterable()
            ->ordered()
            ->get();

        return response()->json(['data' => $filters]);
    }

    public function apply(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'filters' => 'nullable|array',
            'filters.*' => 'string',
        ]);

        $category = Category::findOrFail($validated['category_id']);
        $filters = $category->filters()->active()->filterable()->ordered()->get();

        $businessQuery = $category->businesses()->active();

        if (! empty($validated['filters'])) {
            foreach ($validated['filters'] as $filterKey => $filterValue) {
                $filter = $filters->firstWhere('field_key', $filterKey);
                if ($filter && $filter->is_filterable) {
                    $businessQuery->where(function ($q) use ($filter, $filterValue) {
                        $q->whereJsonContains('metadata->'.$filter->field_key, $filterValue);
                    });
                }
            }
        }

        $businesses = $businessQuery->paginate(20);

        return response()->json(['data' => $businesses]);
    }
}
