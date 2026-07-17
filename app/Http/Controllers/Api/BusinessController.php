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
            ->inServiceableArea()
            ->with([
                'category', 'subcategory', 'area',
                'products' => fn ($q) => $q->where('is_active', true)->limit(4),
                'services' => fn ($q) => $q->where('is_active', true)->limit(4),
                'deliveryZones' => fn ($q) => $q->where('is_active', true),
            ])
            ->when($request->category, function ($query, $category) {
                $query->whereHas('category', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            })
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->when($request->area_id, function ($query, $areaId) {
                $query->where(function ($q) use ($areaId) {
                    $q->whereHas('deliveryZones', function ($q2) use ($areaId) {
                        $q2->where('area_id', $areaId)->where('is_active', true);
                    })->orWhere('area_id', $areaId);
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

    public function featured(Request $request)
    {
        $businesses = Business::active()
            ->inServiceableArea()
            ->featured()
            ->with(['category', 'subcategory', 'area'])
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => $businesses,
        ]);
    }

    public function trending(Request $request)
    {
        $businesses = Business::active()
            ->inServiceableArea()
            ->with(['category', 'subcategory', 'area'])
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => $businesses,
        ]);
    }

    public function newlyAdded(Request $request)
    {
        $businesses = Business::active()
            ->inServiceableArea()
            ->with(['category', 'subcategory', 'area'])
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => $businesses,
        ]);
    }

    public function show($slug)
    {
        $business = Business::active()->inServiceableArea()->where('slug', $slug)
            ->with(['category', 'subcategory', 'area', 'products' => fn ($q) => $q->where('is_active', true)->limit(10), 'deliveryZones' => fn ($q) => $q->where('is_active', true), 'deliveryZones.area', 'vehicles' => fn ($q) => $q->where('is_active', true)->limit(10)])
            ->firstOrFail();

        $business->increment('views_count');

        return response()->json([
            'business' => $business,
        ]);
    }

    public function showById($id)
    {
        $business = Business::active()->inServiceableArea()->with(['category', 'subcategory', 'products'])
            ->findOrFail($id);

        return response()->json([
            'business' => $business,
        ]);
    }

    public function related($slug)
    {
        $business = Business::active()->inServiceableArea()->where('slug', $slug)->firstOrFail();

        $related = Business::active()
            ->inServiceableArea()
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
            ->inServiceableArea()
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
            ->inServiceableArea()
            ->with(['category', 'subcategory', 'area'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->selectRaw('*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance', [$lat, $lng, $lat])
            ->whereRaw('(
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) < ?', [$lat, $lng, $lat, $radius])
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

        $actionField = match ($request->action) {
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

    public function services($slug)
    {
        $business = Business::active()->inServiceableArea()->where('slug', $slug)->firstOrFail();

        $services = $business->services()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(compact('services'));
    }

    public function publicServices($id)
    {
        $business = Business::active()->inServiceableArea()->findOrFail($id);

        $services = $business->services()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(compact('services'));
    }
}
