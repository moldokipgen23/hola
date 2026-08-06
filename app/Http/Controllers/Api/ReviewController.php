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
            ->approved()
            ->with('user')
            ->latest()
            ->paginate(10);

        $stats = [
            'average' => round((float) $business->reviews()->approved()->avg('rating'), 1),
            'count' => $business->reviews()->approved()->count(),
            'distribution' => [
                5 => $business->reviews()->approved()->where('rating', 5)->count(),
                4 => $business->reviews()->approved()->where('rating', 4)->count(),
                3 => $business->reviews()->approved()->where('rating', 3)->count(),
                2 => $business->reviews()->approved()->where('rating', 2)->count(),
                1 => $business->reviews()->approved()->where('rating', 1)->count(),
            ],
        ];

        return response()->json(compact('reviews', 'stats'));
    }

    public function store(Request $request, Business $business)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:4096',
        ]);

        $existing = $business->reviews()->where('user_id', $request->user()->id)->first();
        if ($existing) {
            return response()->json(['message' => 'You have already reviewed this business'], 422);
        }

        $data = [
            'user_id' => $request->user()->id,
            'business_id' => $business->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'status' => 'approved',
        ];

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('reviews', 'public');
        }

        $review = Review::create($data);

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
        if ($review->user_id !== $user->id && ! $user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted.']);
    }

    /**
     * Admin moderation queue — pending, flagged, or filtered reviews.
     */
    public function moderation(Request $request)
    {
        $query = Review::with(['user', 'business:id,name'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('flagged_only') && filter_var($request->flagged_only, FILTER_VALIDATE_BOOL)) {
            $query->whereNotNull('flagged_at');
        }

        $reviews = $query->paginate(20);

        return response()->json([
            'reviews' => $reviews,
            'counts' => [
                'pending' => Review::where('status', Review::STATUS_PENDING)->count(),
                'approved' => Review::where('status', Review::STATUS_APPROVED)->count(),
                'hidden' => Review::where('status', Review::STATUS_HIDDEN)->count(),
                'flagged' => Review::whereNotNull('flagged_at')->count(),
            ],
        ]);
    }

    /**
     * Admin approve / hide / restore moderation action.
     */
    public function moderate(Request $request, Review $review)
    {
        $request->validate([
            'status' => 'required|in:approved,pending,hidden',
            'reason' => 'nullable|string|max:500',
        ]);

        $review->update([
            'status' => $request->status,
            'moderation_reason' => $request->reason,
            'flagged_at' => $request->status === Review::STATUS_HIDDEN
                ? ($review->flagged_at ?? now())
                : $review->flagged_at,
        ]);

        ActivityLogService::log('review_moderated', $review, [
            'business_id' => $review->business_id,
            'status' => $request->status,
            'reason' => $request->reason,
        ]);

        return response()->json(['review' => $review->load(['user', 'business:id,name']), 'message' => 'Review updated.']);
    }
}
