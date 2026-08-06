<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessSummaryResource;
use App\Models\Business;
use App\Services\Experience\BusinessExperienceService;
use App\Services\LaunchControlService;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function __construct(private readonly LaunchControlService $launchControl) {}

    public function index(Request $request)
    {
        $this->ensureRequestedFeatureAvailable($request);
        $query = Business::active()
            ->with([
                'category', 'subcategory', 'area', 'city',
                'products' => fn ($q) => $q->where('is_active', true)->limit(4),
                'services' => fn ($q) => $q->where('is_active', true)->limit(4),
                'deliveryZones' => fn ($q) => $q->where('is_active', true),
                'vehicles' => fn ($q) => $q->where('is_active', true)->limit(4),
            ])
            ->when($request->category, function ($query, $category) {
                $query->whereHas('category', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            })
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->when($request->experience, fn ($q, $exp) => $q->whereJsonContains('enabled_experiences', $exp))
            ->when($request->ready_only, fn ($q) => $q->where(function ($qq) {
                $qq->whereNotNull('enabled_experiences')
                    ->whereRaw('JSON_LENGTH(enabled_experiences) > 1');
            }))
            ->when($request->availability_mode, fn ($q, $mode) => $q->whereJsonContains('experience_config->*.availability_mode', $mode))
            ->when($request->pincode, fn ($q, $p) => $q->where('pincode', $p))
            ->when($request->district, fn ($q, $d) => $q->where('district', $d))
            ->when($request->city_id, function ($q, $cityId) {
                $q->where(function ($qq) use ($cityId) {
                    $qq->where('city_id', $cityId)
                        ->orWhereNull('city_id');
                });
            })
            ->when($request->city, function ($q, $citySlug) {
                $q->where(function ($qq) use ($citySlug) {
                    $qq->whereHas('city', fn ($cityQ) => $cityQ->where('slug', $citySlug))
                        ->orWhereNull('city_id');
                });
            })
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
            ->when($request->latitude && $request->longitude, function ($query) use ($request) {
                $lat = $request->latitude;
                $lng = $request->longitude;
                $radius = $request->radius ?? 10;

                $query->whereNotNull('latitude')
                    ->whereNotNull('longitude')
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
                    ->orderBy('distance');
            })
            ->when($request->sort, function ($query, $sort) {
                match ($sort) {
                    'rating' => $query->orderByDesc('average_rating'),
                    'reviews' => $query->orderByDesc('review_count'),
                    'distance' => $query->orderBy('distance'),
                    'newest' => $query->orderByDesc('created_at'),
                    'popular' => $query->orderByDesc('views_count'),
                    default => $query->orderByDesc('is_featured')->orderByDesc('created_at'),
                };
            }, function ($query) {
                $query->orderByDesc('is_featured')->orderByDesc('created_at');
            });

        $perPage = min($request->integer('per_page', 20), 50);
        $businesses = $query->paginate($perPage);

        return response()->json([
            'businesses' => [
                'data' => BusinessSummaryResource::collection($businesses->load(['category', 'subcategory', 'area', 'city'])),
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'per_page' => $businesses->perPage(),
                'total' => $businesses->total(),
            ],
        ]);
    }

    public function featured(Request $request)
    {
        $this->ensureRequestedFeatureAvailable($request);
        $businesses = Business::active()
            ->featured()
            ->with(['category', 'subcategory', 'area', 'city'])
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->when($request->experience, fn ($q, $exp) => $q->whereJsonContains('enabled_experiences', $exp))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => BusinessSummaryResource::collection($businesses),
        ]);
    }

    public function trending(Request $request)
    {
        $this->ensureRequestedFeatureAvailable($request);
        $businesses = Business::active()
            ->with(['category', 'subcategory', 'area', 'city'])
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->when($request->experience, fn ($q, $exp) => $q->whereJsonContains('enabled_experiences', $exp))
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => BusinessSummaryResource::collection($businesses),
        ]);
    }

    public function newlyAdded(Request $request)
    {
        $this->ensureRequestedFeatureAvailable($request);
        $businesses = Business::active()
            ->with(['category', 'subcategory', 'area', 'city'])
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->when($request->experience, fn ($q, $exp) => $q->whereJsonContains('enabled_experiences', $exp))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'businesses' => BusinessSummaryResource::collection($businesses),
        ]);
    }

    public function show($slug)
    {
        $business = Business::active()->where('slug', $slug)
            ->with([
                'category', 'subcategory', 'area', 'city',
                'products' => fn ($q) => $q->where('is_active', true)->limit(10),
                'services' => fn ($q) => $q->where('is_active', true)->limit(10),
                'deliveryZones' => fn ($q) => $q->where('is_active', true),
                'deliveryZones.area',
                'vehicles' => fn ($q) => $q->where('is_active', true)->limit(10),
            ])
            ->firstOrFail();

        $business->increment('views_count');

        $readiness = app(BusinessExperienceService::class)->calculateReadiness($business);
        $prototypeData = [];
        foreach ($readiness as $exp => $r) {
            if ($r['ready'] && $this->launchControl->experienceEnabled($exp)) {
                $prototypeData[$exp] = app(BusinessExperienceService::class)->getPrototypeSummary($business, $exp);
            }
        }

        return response()->json([
            'business' => new BusinessSummaryResource($business),
            'prototype_data' => $prototypeData,
        ]);
    }

    public function showById($id)
    {
        $business = Business::active()->with(['category', 'subcategory', 'products'])->findOrFail($id);

        return response()->json([
            'business' => new BusinessSummaryResource($business),
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
            'related' => BusinessSummaryResource::collection($related),
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
            'businesses' => BusinessSummaryResource::collection($businesses),
        ]);
    }

    public function nearby(Request $request)
    {
        $this->ensureRequestedFeatureAvailable($request);
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:50',
        ]);

        $lat = $request->latitude;
        $lng = $request->longitude;
        $radius = $request->radius ?? 10;

        $businesses = Business::active()
            ->with(['category', 'subcategory', 'area', 'city'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->module, fn ($q, $m) => $q->ofModule($m))
            ->when($request->experience, fn ($q, $exp) => $q->whereJsonContains('enabled_experiences', $exp))
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
            'businesses' => BusinessSummaryResource::collection($businesses),
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

    private function ensureRequestedFeatureAvailable(Request $request): void
    {
        if ($request->filled('module')) {
            $available = match ($request->string('module')->toString()) {
                'ordering' => $this->launchControl->worldAvailable('shop'),
                'booking' => $this->launchControl->worldAvailable('book'),
                'transport' => $this->launchControl->worldAvailable('ride'),
                'directory' => $this->launchControl->experienceEnabled('directory'),
                default => $this->launchControl->moduleEnabled($request->string('module')->toString()),
            };
            abort_unless($available, 404);
        }

        if ($request->filled('experience')) {
            abort_unless($this->launchControl->experienceEnabled($request->string('experience')->toString()), 404);
        }
    }

    public function services($slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();

        $services = $business->services()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(compact('services'));
    }

    public function publicServices($id)
    {
        $business = Business::active()->findOrFail($id);

        $services = $business->services()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(compact('services'));
    }

    /**
     * Server-side map clustering. Groups geo-tagged businesses into grid cells
     * whose size scales with the request zoom level. At high zoom clusters
     * resolve to individual businesses; at low zoom they collapse into a
     * cluster with centre coordinates + count. Supports an optional viewport
     * (south/west/north/east) so the mobile map only pays for the visible area.
     */
    public function clusters(Request $request)
    {
        $lat = (float) $request->float('latitude');
        $lng = (float) $request->float('longitude');
        $radius = $request->float('radius', 20);
        $zoom = max(1, min(20, (int) $request->integer('zoom', 8)));

        $query = Business::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        $south = $request->input('south') !== null ? $request->float('south') : null;
        $west = $request->input('west') !== null ? $request->float('west') : null;
        $north = $request->input('north') !== null ? $request->float('north') : null;
        $east = $request->input('east') !== null ? $request->float('east') : null;

        if ($request->category) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }
        if ($request->module) {
            $query->ofModule($request->module);
        }
        if ($request->experience) {
            $query->whereJsonContains('enabled_experiences', $request->experience);
        }

        if (is_numeric($south) && is_numeric($west) && is_numeric($north) && is_numeric($east)) {
            $query->whereBetween('latitude', [min($south, $north), max($south, $north)])
                ->whereBetween('longitude', [min($west, $east), max($west, $east)]);
        } elseif ($lat !== 0.0 || $lng !== 0.0) {
            $query->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereRaw('(
                    6371 * acos(min(1, max(-1,
                        cos(radians(?)) * cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) +
                        sin(radians(?)) * sin(radians(latitude))
                    )))
                ) < ?', [$lat, $lng, $lat, $radius]);
        }

        $businesses = $query->get(['id', 'name', 'slug', 'latitude', 'longitude', 'photos', 'average_rating']);

        $geohashPrecision = max(1, min(12, $zoom <= 6 ? 3 : ($zoom <= 8 ? 4 : ($zoom <= 10 ? 5 : ($zoom <= 12 ? 6 : ($zoom <= 14 ? 7 : 9))))));

        $clusters = [];
        foreach ($businesses as $business) {
            $hash = $this->geohash((float) $business->latitude, (float) $business->longitude, $geohashPrecision);
            $clusters[$hash][] = $business;
        }

        $items = [];
        foreach ($clusters as $hash => $members) {
            $count = count($members);
            $avgLat = array_sum(array_map(fn ($m) => (float) $m->latitude, $members)) / $count;
            $avgLng = array_sum(array_map(fn ($m) => (float) $m->longitude, $members)) / $count;
            $top = $members[0];

            $items[] = [
                'latitude' => round($avgLat, 6),
                'longitude' => round($avgLng, 6),
                'count' => $count,
                'is_cluster' => $count > 1,
                'representative' => [
                    'id' => $top->id,
                    'name' => $top->name,
                    'slug' => $top->slug,
                    'rating' => (float) $top->average_rating,
                    'photo' => is_array($top->photos) && count($top->photos) > 0 ? $top->photos[0] : null,
                ],
            ];
        }

        return response()->json([
            'zoom' => $zoom,
            'cells' => $items,
            'total' => $businesses->count(),
        ]);
    }

    /**
     * Simple base-32 geohash with the requested precision. Lat/lng are interleaved
     * bit-wise using the canonical 90/180 ranges so equal precision yields equal
     * cell sizes in both axes.
     */
    private function geohash(float $lat, float $lng, int $precision): string
    {
        $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
        $latMin = -90.0;
        $latMax = 90.0;
        $lngMin = -180.0;
        $lngMax = 180.0;
        $bit = 0;
        $ch = 0;
        $even = true;
        $hash = '';

        while (strlen($hash) < $precision) {
            if ($even) {
                $mid = ($lngMin + $lngMax) / 2;
                if ($lng >= $mid) {
                    $ch |= (1 << (4 - $bit));
                    $lngMin = $mid;
                } else {
                    $lngMax = $mid;
                }
            } else {
                $mid = ($latMin + $latMax) / 2;
                if ($lat >= $mid) {
                    $ch |= (1 << (4 - $bit));
                    $latMin = $mid;
                } else {
                    $latMax = $mid;
                }
            }

            $even = ! $even;
            $bit++;
            if ($bit === 5) {
                $hash .= $base32[$ch];
                $bit = 0;
                $ch = 0;
            }
        }

        return $hash;
    }
}
