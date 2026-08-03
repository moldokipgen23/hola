<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\World;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $query = $request->q;
        $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';
        $world = $request->get('world');

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

        $services = Service::where('is_active', true)
            ->where('name', 'like', $safe)
            ->with('business')
            ->limit(10)
            ->get();

        $sections = [];

        if ($businesses->isNotEmpty()) {
            $sections[] = [
                'type' => 'businesses',
                'label' => 'Businesses',
                'items' => $businesses,
            ];
        }

        if ($categories->isNotEmpty()) {
            $sections[] = [
                'type' => 'categories',
                'label' => 'Categories',
                'items' => $categories,
            ];
        }

        if ($products->isNotEmpty()) {
            $sections[] = [
                'type' => 'products',
                'label' => 'Products',
                'items' => $products,
            ];
        }

        if ($services->isNotEmpty()) {
            $sections[] = [
                'type' => 'services',
                'label' => 'Services',
                'items' => $services,
            ];
        }

        return response()->json([
            'query' => $query,
            'total' => $businesses->count() + $categories->count() + $products->count() + $services->count(),
            'sections' => $sections,
        ]);
    }

    public function suggestions(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1',
        ]);

        $query = $request->q;
        $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

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

        $serviceSuggestions = Service::where('is_active', true)
            ->where('name', 'like', $safe)
            ->pluck('name')
            ->take(3)
            ->toArray();

        return response()->json([
            'suggestions' => array_merge($suggestions, $categorySuggestions, $productSuggestions, $serviceSuggestions),
        ]);
    }

    public function universal(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $query = $request->q;
        $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';
        $worldSlug = $request->get('world');
        $limit = min((int) $request->get('per_page', 10), 25);

        $world = null;
        if ($worldSlug) {
            $world = World::where('slug', $worldSlug)->where('is_active', true)->first();
        }

        $businessQuery = Business::active()->search($query)->with(['category', 'area']);
        if ($world) {
            $businessQuery->whereHas('classifications', fn($q) => $q->where('world_id', $world->id));
        }
        $businesses = $businessQuery->limit($limit)->get();

        $categoryQuery = Category::active()->where('name', 'like', $safe);
        if ($world) {
            $categoryQuery->where('world_id', $world->id);
        }
        $categories = $categoryQuery->limit(5)->get();

        $productQuery = Product::active()->where('name', 'like', $safe)->with('business');
        if ($world) {
            $productQuery->whereHas('business', fn($q) => $q->whereHas('classifications', fn($q2) => $q2->where('world_id', $world->id)));
        }
        $products = $productQuery->limit($limit)->get();

        $serviceQuery = Service::where('is_active', true)->where('name', 'like', $safe)->with('business');
        if ($world) {
            $serviceQuery->whereHas('business', fn($q) => $q->whereHas('classifications', fn($q2) => $q2->where('world_id', $world->id)));
        }
        $services = $serviceQuery->limit($limit)->get();

        $sections = [];

        if ($businesses->isNotEmpty()) {
            $sections[] = [
                'type' => 'businesses',
                'label' => 'Businesses',
                'items' => $businesses->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'category' => $b->category?->name,
                    'rating' => $b->rating,
                    'review_count' => $b->review_count,
                    'area' => $b->area?->name,
                    'photo' => $b->photo,
                ]),
            ];
        }

        if ($categories->isNotEmpty()) {
            $sections[] = [
                'type' => 'categories',
                'label' => 'Categories',
                'items' => $categories->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'icon' => $c->icon,
                    'world' => $c->world?->name,
                ]),
            ];
        }

        if ($products->isNotEmpty()) {
            $sections[] = [
                'type' => 'products',
                'label' => 'Products',
                'items' => $products->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price' => $p->price,
                    'business_name' => $p->business?->name,
                    'photo' => $p->photo,
                ]),
            ];
        }

        if ($services->isNotEmpty()) {
            $sections[] = [
                'type' => 'services',
                'label' => 'Services',
                'items' => $services->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'price' => $s->price,
                    'booking_mode' => $s->booking_mode,
                    'business_name' => $s->business?->name,
                ]),
            ];
        }

        return response()->json([
            'query' => $query,
            'world' => $worldSlug,
            'total' => $sections->sum(fn($s) => $s['items']->count()),
            'sections' => $sections,
        ]);
    }
}
