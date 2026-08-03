<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SearchAnalytic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchAnalyticsController extends Controller
{
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'world' => 'nullable|string|max:50',
            'results_count' => 'required|integer|min:0',
            'session_id' => 'nullable|string|max:100',
        ]);

        SearchAnalytic::create([
            'query' => $validated['query'],
            'user_id' => $request->user()?->id,
            'world' => $validated['world'] ?? null,
            'results_count' => $validated['results_count'],
            'session_id' => $validated['session_id'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    public function click(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'business_id' => 'required|integer|exists:businesses,id',
            'position' => 'required|integer|min:0',
            'session_id' => 'nullable|string|max:100',
        ]);

        SearchAnalytic::create([
            'query' => $validated['query'],
            'user_id' => $request->user()?->id,
            'clicked_business_id' => $validated['business_id'],
            'clicked_position' => $validated['position'],
            'session_id' => $validated['session_id'] ?? null,
            'results_count' => 0,
        ]);

        return response()->json(['success' => true]);
    }
}
