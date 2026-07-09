<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $businesses = Business::active()
            ->with(['category', 'subcategory', 'products'])
            ->when($request->category, function ($query, $category) {
                $query->whereHas('category', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            })
            ->when($request->featured, function ($query) {
                $query->featured();
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'businesses' => $businesses,
        ]);
    }

    public function featured()
    {
        $businesses = Business::active()
            ->featured()
            ->with(['category', 'subcategory'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => $businesses,
        ]);
    }

    public function trending()
    {
        $businesses = Business::active()
            ->with(['category', 'subcategory'])
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => $businesses,
        ]);
    }

    public function newlyAdded()
    {
        $businesses = Business::active()
            ->with(['category', 'subcategory'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => $businesses,
        ]);
    }

    public function show($slug)
    {
        $business = Business::active()->where('slug', $slug)
            ->with(['category', 'subcategory', 'products'])
            ->firstOrFail();

        $business->increment('views_count');

        return response()->json([
            'business' => $business,
        ]);
    }

    public function showById($id)
    {
        $business = Business::active()->with(['category', 'subcategory', 'products'])
            ->findOrFail($id);

        return response()->json([
            'business' => $business,
        ]);
    }

    public function related($slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();

        $related = Business::active()
            ->where('category_id', $business->category_id)
            ->where('id', '!=', $business->id)
            ->with(['category', 'subcategory'])
            ->limit(6)
            ->get();

        return response()->json([
            'related' => $related,
        ]);
    }

    public function byCategory($categorySlug)
    {
        $businesses = Business::active()
            ->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            })
            ->with(['category', 'subcategory'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'businesses' => $businesses,
        ]);
    }

    public function nearby(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:50',
        ]);

        $lat = $request->latitude;
        $lng = $request->longitude;
        $radius = $request->radius ?? 5;

        $businesses = Business::active()
            ->with(['category', 'subcategory'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance", [$lat, $lng, $lat])
            ->whereRaw("(
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) < ?", [$lat, $lng, $lat, $radius])
            ->orderBy('distance')
            ->limit(20)
            ->get();

        return response()->json([
            'businesses' => $businesses,
        ]);
    }

    public function trackAction(Request $request, $slug)
    {
        $request->validate([
            'action' => 'required|in:call,whatsapp,directions,share',
        ]);

        $business = Business::where('slug', $slug)->firstOrFail();

        $actionField = match($request->action) {
            'call' => 'call_count',
            'whatsapp' => 'whatsapp_count',
            'directions' => 'directions_count',
            'share' => 'share_count',
            default => null,
        };

        if ($actionField) {
            $business->increment($actionField);
        }

        return response()->json(['success' => true]);
    }
}
