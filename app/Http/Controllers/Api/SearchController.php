<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $query = $request->q;
        $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        $businesses = Business::active()
            ->search($query)
            ->with(['category', 'subcategory'])
            ->limit(10)
            ->get();

        $categories = Category::active()
            ->where('name', 'like', $safe)
            ->limit(5)
            ->get();

        $products = Product::active()
            ->where('name', 'like', $safe)
            ->with('business')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => $businesses,
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    public function suggestions(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1',
        ]);

        $query = $request->q;
        $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        $suggestions = Business::active()
            ->where('name', 'like', $safe)
            ->pluck('name')
            ->take(5)
            ->toArray();

        $categorySuggestions = Category::active()
            ->where('name', 'like', $safe)
            ->pluck('name')
            ->take(3)
            ->toArray();

        $productSuggestions = Product::active()
            ->where('name', 'like', $safe)
            ->pluck('name')
            ->take(5)
            ->toArray();

        return response()->json([
            'suggestions' => array_merge($suggestions, $categorySuggestions, $productSuggestions),
        ]);
    }
}
