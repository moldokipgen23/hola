<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::active()
            ->with('business')
            ->when($request->business_id, function ($query, $businessId) {
                $query->where('business_id', $businessId);
            })
            ->orderBy('order')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'products' => $products,
        ]);
    }

    public function show($slug)
    {
        $product = Product::where('slug', $slug)
            ->with('business')
            ->firstOrFail();

        return response()->json([
            'product' => $product,
        ]);
    }

    public function byBusiness($businessId)
    {
        $products = Product::active()
            ->where('business_id', $businessId)
            ->orderBy('order')
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }

    public function popular()
    {
        $products = Product::active()
            ->with('business')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }
}
