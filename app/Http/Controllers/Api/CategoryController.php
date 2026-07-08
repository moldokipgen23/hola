<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::active()
            ->with('subcategories')
            ->orderBy('order')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function featured()
    {
        $categories = Category::active()
            ->featured()
            ->with('subcategories')
            ->orderBy('order')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function show($slug)
    {
        $category = Category::where('slug', $slug)
            ->with(['subcategories', 'businesses' => function ($query) {
                $query->active()->limit(20);
            }])
            ->firstOrFail();

        return response()->json([
            'category' => $category,
        ]);
    }

    public function showWithBusinesses($slug, Request $request)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $businesses = $category->businesses()
            ->active()
            ->with(['subcategory', 'products'])
            ->when($request->subcategory, function ($query, $subcategory) {
                $query->where('subcategory_id', $subcategory);
            })
            ->when($request->featured, function ($query) {
                $query->featured();
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'category' => $category,
            'businesses' => $businesses,
        ]);
    }
}
