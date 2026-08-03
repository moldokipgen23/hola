<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $products = Product::active()
            ->whereHas('business', fn ($query) => $query->active()->ofModule('catalog'))
            ->with('business:id,name,slug,is_active,enabled_modules')
            ->when($request->business_id, function ($query, $businessId) {
                $query->where('business_id', $businessId);
            })
            ->orderBy('order')
            ->paginate($perPage);

        return response()->json([
            'products' => $products,
        ]);
    }

    public function show($slug)
    {
        $product = Product::active()
            ->where('slug', $slug)
            ->whereHas('business', fn ($query) => $query->active()->ofModule('catalog'))
            ->with('business:id,name,slug,is_active,enabled_modules')
            ->firstOrFail();

        return response()->json([
            'product' => $product,
        ]);
    }

    public function byBusiness($businessId)
    {
        $products = Product::active()
            ->where('business_id', $businessId)
            ->whereHas('business', fn ($query) => $query->active()->ofModule('catalog'))
            ->orderBy('order')
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }

    public function popular()
    {
        $products = Product::active()
            ->whereHas('business', fn ($query) => $query->active()->ofModule('catalog'))
            ->with('business:id,name,slug,is_active,enabled_modules')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }
}
