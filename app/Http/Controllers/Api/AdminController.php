<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use App\Models\ClaimRequest;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\Report;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // ─── Businesses ───

    public function storeBusiness(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'subcategory_id' => 'nullable|exists:subcategories,id',
            'description' => 'nullable|string',
            'address' => 'required|string|max:255',
            'locality' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'pincode' => 'required|string|size:6',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'working_hours' => 'nullable|array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:2048',
        ]);

        // Validate pincode
        $pincode = Pincode::lookup($request->pincode);
        if (! $pincode) {
            return response()->json(['message' => 'Invalid pincode.'], 422);
        }
        if (! $pincode->serviceable) {
            return response()->json([
                'message' => "Cannot create business in {$pincode->district}, {$pincode->state}. This area is not yet serviceable.",
            ], 422);
        }

        $data = $request->only([
            'name', 'category_id', 'subcategory_id', 'description',
            'address', 'locality', 'district', 'latitude', 'longitude',
            'phone', 'whatsapp', 'email', 'website', 'working_hours', 'is_featured', 'is_active',
        ]);

        $data['pincode'] = $pincode->pincode;
        $data['state'] = $pincode->state;
        $data['slug'] = Str::slug($request->name);
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('photos')) {
            $photos = [];
            foreach ($request->file('photos') as $photo) {
                $photos[] = $photo->store('businesses', 'public');
            }
            $data['photos'] = $photos;
        }

        $business = Business::create($data);

        return response()->json(['business' => $business], 201);
    }

    public function updateBusiness(Request $request, $id)
    {
        $business = Business::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'subcategory_id' => 'nullable|exists:subcategories,id',
            'description' => 'nullable|string',
            'address' => 'sometimes|required|string|max:255',
            'locality' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'pincode' => 'sometimes|required|string|size:6',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'working_hours' => 'nullable|array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'verification_status' => 'nullable|in:pending,verified,rejected',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:2048',
        ]);

        $data = $request->only([
            'name', 'category_id', 'subcategory_id', 'description',
            'address', 'locality', 'district', 'latitude', 'longitude',
            'phone', 'whatsapp', 'email', 'website', 'working_hours', 'is_featured', 'is_active',
            'verification_status',
        ]);

        // Handle pincode update
        if ($request->has('pincode')) {
            $pincode = Pincode::lookup($request->pincode);
            if (! $pincode) {
                return response()->json(['message' => 'Invalid pincode.'], 422);
            }
            if (! $pincode->serviceable) {
                return response()->json([
                    'message' => "Cannot set business to {$pincode->district}, {$pincode->state}. This area is not yet serviceable.",
                ], 422);
            }
            $data['pincode'] = $pincode->pincode;
            $data['state'] = $pincode->state;
            $data['district'] = $data['district'] ?? $pincode->district;
        }

        if ($request->has('name')) {
            $data['slug'] = Str::slug($request->name);
        }

        if ($request->hasFile('photos')) {
            $photos = $business->photos ?? [];
            foreach ($request->file('photos') as $photo) {
                $photos[] = $photo->store('businesses', 'public');
            }
            $data['photos'] = $photos;
        }

        $business->update($data);

        return response()->json(['business' => $business]);
    }

    public function destroyBusiness($id)
    {
        $business = Business::findOrFail($id);
        $business->delete();

        return response()->json(['message' => 'Business deleted.']);
    }

    public function toggleBusiness(Request $request, $id)
    {
        $business = Business::findOrFail($id);

        $request->validate([
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->has('is_featured')) {
            $business->is_featured = $request->is_featured;
        }
        if ($request->has('is_active')) {
            $business->is_active = $request->is_active;
        }

        $business->save();

        return response()->json(['business' => $business]);
    }

    public function verifyBusiness(Request $request, $id)
    {
        $business = Business::findOrFail($id);

        $request->validate([
            'verification_status' => 'required|in:pending,verified,rejected',
        ]);

        $business->verification_status = $request->verification_status;
        $business->save();

        return response()->json(['business' => $business]);
    }

    // ─── Categories ───

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:10',
            'image' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $category = Category::create([
            ...$request->only(['name', 'icon', 'image', 'order', 'is_featured', 'is_active']),
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['category' => $category], 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'icon' => 'nullable|string|max:10',
            'image' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['name', 'icon', 'image', 'order', 'is_featured', 'is_active']);

        if ($request->has('name')) {
            $data['slug'] = Str::slug($request->name);
        }

        $category->update($data);

        return response()->json(['category' => $category]);
    }

    public function destroyCategory($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    // ─── Subcategories ───

    public function storeSubcategory(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:10',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $subcategory = Subcategory::create([
            ...$request->only(['category_id', 'name', 'icon', 'order', 'is_active']),
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['subcategory' => $subcategory], 201);
    }

    public function updateSubcategory(Request $request, $id)
    {
        $subcategory = Subcategory::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'icon' => 'nullable|string|max:10',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['name', 'icon', 'order', 'is_active']);

        if ($request->has('name')) {
            $data['slug'] = Str::slug($request->name);
        }

        $subcategory->update($data);

        return response()->json(['subcategory' => $subcategory]);
    }

    public function destroySubcategory($id)
    {
        $subcategory = Subcategory::findOrFail($id);
        $subcategory->delete();

        return response()->json(['message' => 'Subcategory deleted.']);
    }

    // ─── Products ───

    public function storeProduct(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'availability' => 'nullable|in:in_stock,out_of_stock,limited',
            'is_active' => 'boolean',
        ]);

        $product = Product::create([
            ...$request->only(['business_id', 'name', 'description', 'image', 'price', 'availability', 'is_active']),
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['product' => $product], 201);
    }

    public function updateProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'availability' => 'nullable|in:in_stock,out_of_stock,limited',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['name', 'description', 'image', 'price', 'availability', 'is_active']);

        if ($request->has('name')) {
            $data['slug'] = Str::slug($request->name);
        }

        $product->update($data);

        return response()->json(['product' => $product]);
    }

    public function destroyProduct($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    // ─── Reports ───

    public function indexReports()
    {
        $reports = Report::with(['business', 'user'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['reports' => $reports]);
    }

    public function updateReport(Request $request, $id)
    {
        $report = Report::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,reviewed,resolved',
            'admin_notes' => 'nullable|string',
        ]);

        $report->update($request->only(['status', 'admin_notes']));

        return response()->json(['report' => $report]);
    }

    // ─── Analytics ───

    public function analytics()
    {
        $totalViews = Business::sum('views_count');
        $totalSaves = Business::sum('saves_count');
        $totalCalls = Business::sum('call_count');
        $totalWhatsapps = Business::sum('whatsapp_count');
        $totalDirections = Business::sum('directions_count');
        $totalShares = Business::sum('share_count');
        $topBusinesses = Business::orderByDesc('views_count')->limit(10)->get();
        $recentReports = Report::orderByDesc('created_at')->limit(10)->get();

        return response()->json([
            'total_views' => $totalViews,
            'total_saves' => $totalSaves,
            'total_calls' => $totalCalls,
            'total_whatsapps' => $totalWhatsapps,
            'total_directions' => $totalDirections,
            'total_shares' => $totalShares,
            'total_businesses' => Business::count(),
            'total_categories' => Category::count(),
            'total_products' => Product::count(),
            'total_reports' => Report::count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'total_claims' => ClaimRequest::count(),
            'pending_claims' => ClaimRequest::where('status', 'pending')->count(),
            'featured_businesses' => Business::where('is_featured', true)->count(),
            'verified_businesses' => Business::where('verification_status', 'verified')->count(),
            'top_businesses' => $topBusinesses,
            'recent_reports' => $recentReports,
        ]);
    }
}
