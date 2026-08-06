<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassificationAuditController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('businesses')
            ->leftJoin('business_classifications', function ($j) {
                $j->on('businesses.id', '=', 'business_classifications.business_id')
                    ->where('business_classifications.is_active', '=', 1);
            })
            ->leftJoin('categories', 'businesses.category_id', '=', 'categories.id')
            ->leftJoin('worlds', 'categories.world_id', '=', 'worlds.id')
            ->select(
                'businesses.id',
                'businesses.name as business_name',
                'businesses.is_active as business_active',
                'categories.name as category_name',
                'worlds.name as world_name',
                'business_classifications.id as classification_id',
                'business_classifications.is_active as classification_active'
            );

        if ($filter = request('filter')) {
            if ($filter === 'unclassified') {
                $query->whereNull('business_classifications.id');
            } elseif ($filter === 'inactive') {
                $query->where('business_classifications.is_active', false)
                    ->orWhereNull('business_classifications.id');
            } elseif ($filter === 'classified') {
                $query->where('business_classifications.is_active', true);
            }
        }

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($safe) {
                $q->where('businesses.name', 'like', $safe)
                    ->orWhere('categories.name', 'like', $safe);
            });
        }

        $businesses = $query->orderBy('businesses.name')->paginate(30)->withQueryString();

        $stats = $this->getStats();

        return view('admin.classification-audit.index', compact('businesses', 'stats'));
    }

    public function fix(Request $request): JsonResponse
    {
        $unclassified = DB::table('businesses')
            ->leftJoin('business_classifications', function ($j) {
                $j->on('businesses.id', '=', 'business_classifications.business_id')
                    ->where('business_classifications.is_active', '=', 1);
            })
            ->whereNull('business_classifications.id')
            ->where('businesses.is_active', '=', 1)
            ->select('businesses.id', 'businesses.name', 'businesses.category_id', 'businesses.slug')
            ->get();

        if ($unclassified->isEmpty()) {
            return response()->json([
                'message' => 'All active businesses are classified.',
                'classified' => 0,
                'remaining' => 0,
            ]);
        }

        $categoryWorlds = DB::table('categories')
            ->where('is_active', 1)
            ->pluck('world_id', 'id')
            ->toArray();

        $created = 0;

        foreach ($unclassified as $business) {
            $categoryId = $business->category_id;
            $worldId = $categoryWorlds[$categoryId] ?? 1;

            $existing = DB::table('business_classifications')
                ->where('business_id', $business->id)
                ->first();

            if ($existing) {
                DB::table('business_classifications')
                    ->where('business_id', $business->id)
                    ->update(['is_active' => 1, 'is_primary' => 1, 'updated_at' => now()]);
                $created++;
            } else {
                DB::table('business_classifications')->insert([
                    'business_id' => $business->id,
                    'category_id' => $categoryId,
                    'world_id' => $worldId,
                    'is_primary' => 1,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            }
        }

        $remaining = DB::table('businesses')
            ->leftJoin('business_classifications', function ($j) {
                $j->on('businesses.id', '=', 'business_classifications.business_id')
                    ->where('business_classifications.is_active', '=', 1);
            })
            ->whereNull('business_classifications.id')
            ->where('businesses.is_active', '=', 1)
            ->count();

        return response()->json([
            'message' => "Classified {$created} businesses.",
            'classified' => $created,
            'remaining' => $remaining,
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->getStats());
    }

    protected function getStats(): array
    {
        $total = DB::table('businesses')->where('is_active', 1)->count();

        $classified = DB::table('businesses')
            ->join('business_classifications', function ($j) {
                $j->on('businesses.id', '=', 'business_classifications.business_id')
                    ->where('business_classifications.is_active', '=', 1);
            })
            ->where('businesses.is_active', 1)
            ->count();

        $unclassified = $total - $classified;

        $inactive = DB::table('businesses')
            ->leftJoin('business_classifications', function ($j) {
                $j->on('businesses.id', '=', 'business_classifications.business_id');
            })
            ->where('businesses.is_active', 1)
            ->where(function ($q) {
                $q->whereNull('business_classifications.id')
                    ->orWhere('business_classifications.is_active', 0);
            })
            ->count();

        return [
            'total' => $total,
            'classified' => $classified,
            'unclassified' => $unclassified,
            'inactive' => $inactive,
            'percentage' => $total > 0 ? round(($classified / $total) * 100, 1) : 0,
        ];
    }
}
