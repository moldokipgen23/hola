<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\Review;
use App\Models\Category;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OwnerDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $businesses = Business::where('created_by', $request->user()->id)
            ->withCount(['reviews', 'reports', 'claimRequests', 'products'])
            ->get();

        $totalViews = $businesses->sum('views_count');
        $totalCalls = $businesses->sum('call_count');
        $totalWhatsApps = $businesses->sum('whatsapp_count');
        $totalDirections = $businesses->sum('directions_count');
        $totalSaves = $businesses->sum('saves_count');
        $totalShares = $businesses->sum('share_count');

        $recentConversations = \App\Models\Conversation::whereIn('business_id', $businesses->pluck('id'))
            ->with(['user:id,name', 'business:id,name'])
            ->latest('last_message_at')
            ->take(5)
            ->get();

        $recentReviews = Review::whereIn('business_id', $businesses->pluck('id'))
            ->with('user:id,name')
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'businesses' => $businesses,
            'stats' => compact('totalViews', 'totalCalls', 'totalWhatsApps', 'totalDirections', 'totalSaves', 'totalShares'),
            'recentConversations' => $recentConversations,
            'recentReviews' => $recentReviews,
        ]);
    }

    public function businesses(Request $request)
    {
        $businesses = Business::where('created_by', $request->user()->id)
            ->withCount(['reviews', 'reports', 'products'])
            ->with('category')
            ->latest()
            ->get();

        return response()->json(compact('businesses'));
    }

    public function showBusiness(Request $request, $id)
    {
        $business = Business::where('created_by', $request->user()->id)
            ->with(['category', 'products', 'reviews' => fn($q) => $q->with('user:id,name')->latest()])
            ->findOrFail($id);

        return response()->json(compact('business'));
    }

    public function updateBusiness(Request $request, $id)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'address' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'working_hours' => 'nullable|array',
        ]);

        $business->update($validated);

        return response()->json([
            'message' => 'Business updated.',
            'business' => $business,
        ]);
    }

    public function updatePhotos(Request $request, $id)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($id);

        $request->validate([
            'photos' => 'required|array|max:10',
            'photos.*' => 'image|max:2048',
        ]);

        $photos = $business->photos ?? [];

        foreach ($request->file('photos') as $file) {
            $filename = 'businesses/' . $business->slug . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($file));
            $photos[] = 'storage/' . $filename;
        }

        $business->update(['photos' => $photos]);

        return response()->json([
            'message' => 'Photos updated.',
            'photos' => $photos,
        ]);
    }

    public function deletePhoto(Request $request, $id)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($id);

        $request->validate(['photo_index' => 'required|integer']);

        $photos = $business->photos ?? [];
        $index = $request->photo_index;

        if (isset($photos[$index])) {
            $photoPath = str_replace('storage/', '', $photos[$index]);
            Storage::disk('public')->delete($photoPath);
            array_splice($photos, $index, 1);
            $business->update(['photos' => $photos]);
        }

        return response()->json(['message' => 'Photo deleted.', 'photos' => $photos]);
    }

    // Products
    public function storeProduct(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'is_available' => 'boolean',
        ]);

        $validated['business_id'] = $business->id;
        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);

        if ($request->hasFile('image')) {
            $filename = 'products/' . $validated['slug'] . '.' . $request->file('image')->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($request->file('image')));
            $validated['image'] = 'storage/' . $filename;
        }

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Product added.',
            'product' => $product,
        ]);
    }

    public function updateProduct(Request $request, $businessId, $productId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $product = Product::where('business_id', $business->id)->findOrFail($productId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'is_available' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $filename = 'products/' . $product->slug . '.' . $request->file('image')->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($request->file('image')));
            $validated['image'] = 'storage/' . $filename;
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated.',
            'product' => $product,
        ]);
    }

    public function destroyProduct($businessId, $productId)
    {
        $business = Business::where('created_by', request()->user()->id)->findOrFail($businessId);
        $product = Product::where('business_id', $business->id)->findOrFail($productId);

        if ($product->image) {
            Storage::disk('public')->delete(str_replace('storage/', '', $product->image));
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    // Reviews
    public function respondToReview(Request $request, $reviewId)
    {
        $review = Review::with('business')->findOrFail($reviewId);

        if ($review->business->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate(['owner_response' => 'required|string|max:1000']);

        $review->update(['owner_response' => $request->owner_response]);

        return response()->json([
            'message' => 'Response added.',
            'review' => $review,
        ]);
    }

    // Analytics
    public function businessAnalytics(Request $request, $id)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($id);

        return response()->json([
            'business' => $business->only(['id', 'name', 'views_count', 'saves_count', 'call_count', 'whatsapp_count', 'directions_count', 'share_count']),
            'reviews_count' => $business->reviews()->count(),
            'average_rating' => $business->average_rating,
            'products_count' => $business->products()->count(),
        ]);
    }

    // Claim Settings
    public function getClaimSettings(Request $request, $id)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($id);

        return response()->json([
            'claim_notifications_enabled' => (bool) $business->claim_notifications_enabled,
            'claim_notification_delay_days' => (int) $business->claim_notification_delay_days,
            'claim_preferred_channel' => $business->claim_preferred_channel,
            'claim_auto_approve' => (bool) $business->claim_auto_approve,
        ]);
    }

    public function updateClaimSettings(Request $request, $id)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'claim_notifications_enabled' => 'sometimes|boolean',
            'claim_notification_delay_days' => 'sometimes|integer|min:1|max:30',
            'claim_preferred_channel' => 'sometimes|in:all,email,telegram,whatsapp',
            'claim_auto_approve' => 'sometimes|boolean',
        ]);

        $business->update($validated);

        return response()->json([
            'message' => 'Claim settings updated.',
            'settings' => [
                'claim_notifications_enabled' => (bool) $business->claim_notifications_enabled,
                'claim_notification_delay_days' => (int) $business->claim_notification_delay_days,
                'claim_preferred_channel' => $business->claim_preferred_channel,
                'claim_auto_approve' => (bool) $business->claim_auto_approve,
            ],
        ]);
    }
}
