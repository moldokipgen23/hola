<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Review;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Business $business)
    {
        $reviews = $business->reviews()
            ->with('user')
            ->latest()
            ->paginate(10);

        $stats = [
            'average' => round($business->reviews()->avg('rating'), 1),
            'count' => $business->reviews()->count(),
            'distribution' => [
                5 => $business->reviews()->where('rating', 5)->count(),
                4 => $business->reviews()->where('rating', 4)->count(),
                3 => $business->reviews()->where('rating', 3)->count(),
                2 => $business->reviews()->where('rating', 2)->count(),
                1 => $business->reviews()->where('rating', 1)->count(),
            ],
        ];

        return response()->json(compact('reviews', 'stats'));
    }

    public function store(Request $request, Business $business)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $existing = $business->reviews()->where('user_id', $request->user()->id)->first();
        if ($existing) {
            return response()->json(['message' => 'You have already reviewed this business'], 422);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'business_id' => $business->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        $review->load('user');

        NotificationService::reviewCreated($review);
        ActivityLogService::log('review_created', $review, ['business_id' => $business->id, 'rating' => $review->rating]);

        return response()->json([
            'review' => $review,
            'message' => 'Review submitted successfully.',
        ]);
    }

    public function update(Request $request, Review $review)
    {
        if ($review->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review->update($request->only(['rating', 'comment']));

        return response()->json([
            'review' => $review->load('user'),
            'message' => 'Review updated.',
        ]);
    }

    public function destroy(Request $request, Review $review)
    {
        $user = $request->user();
        if ($review->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted.']);
    }
}
