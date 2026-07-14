<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\Review;
use App\Models\Category;
use App\Models\Service;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OwnerDashboardController extends Controller
{
    // Default module configuration per business type
    protected const DEFAULT_MODULES = [
        'catalog' => true,
        'bookings' => false,
        'orders' => false,
        'inventory' => false,
    ];

    // Module defaults by service_type
    protected const SERVICE_TYPE_MODULES = [
        'directory' => ['catalog' => true],
        'bookable' => ['catalog' => true, 'bookings' => true],
        'buyable' => ['catalog' => true, 'orders' => true, 'inventory' => true],
        'hybrid' => ['catalog' => true, 'bookings' => true, 'orders' => true],
    ];

    public function dashboard(Request $request)
    {
        $businesses = Business::where('created_by', $request->user()->id)
            ->withCount(['reviews', 'reports', 'claimRequests', 'products', 'bookings', 'orders'])
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

        // Aggregate module stats across all businesses
        $totalBookings = $businesses->sum('bookings_count');
        $totalOrders = $businesses->sum('orders_count');
        $pendingBookings = Booking::whereIn('business_id', $businesses->pluck('id'))
            ->where('status', 'pending')
            ->count();
        $pendingOrders = Order::whereIn('business_id', $businesses->pluck('id'))
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        return response()->json([
            'businesses' => $businesses,
            'stats' => compact(
                'totalViews', 'totalCalls', 'totalWhatsApps', 'totalDirections', 'totalSaves', 'totalShares',
                'totalBookings', 'totalOrders', 'pendingBookings', 'pendingOrders'
            ),
            'recentConversations' => $recentConversations,
            'recentReviews' => $recentReviews,
        ]);
    }

    // Unified dashboard for a single business with all modules
    public function businessDashboard(Request $request, $id)
    {
        $business = Business::where('created_by', $request->user()->id)
            ->with([
                'category',
                'products' => fn($q) => $q->latest()->take(10),
                'services' => fn($q) => $q->orderBy('sort_order'),
                'bookings' => fn($q) => $q->where('status', 'pending')->where('booking_date', '>=', now()->toDateString())->latest('booking_date')->take(10),
                'orders' => fn($q) => $q->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery'])->latest()->take(10),
            ])
            ->findOrFail($id);

        $enabledModules = $business->enabled_modules ?? self::getDefaultModules($business->service_type);

        $modules = [
            'catalog' => [
                'enabled' => $enabledModules['catalog'] ?? false,
                'products' => $business->products,
                'stats' => [
                    'total' => $business->products_count ?? $business->products()->count(),
                    'active' => $business->products()->where('is_active', true)->count(),
                    'low_stock' => 0, // Will be calculated if inventory module enabled
                ],
            ],
            'bookings' => [
                'enabled' => $enabledModules['bookings'] ?? false,
                'services' => $business->services,
                'upcoming' => $business->bookings,
                'stats' => [
                    'today' => $business->bookings()->where('booking_date', now()->toDateString())->count(),
                    'this_week' => $business->bookings()->whereBetween('booking_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->count(),
                    'pending' => $business->bookings()->where('status', 'pending')->count(),
                    'total' => $business->bookings_count ?? $business->bookings()->count(),
                ],
            ],
            'orders' => [
                'enabled' => $enabledModules['orders'] ?? false,
                'recent' => $business->orders,
                'stats' => [
                    'pending' => $business->orders()->whereIn('status', ['pending', 'confirmed'])->count(),
                    'preparing' => $business->orders()->where('status', 'preparing')->count(),
                    'ready' => $business->orders()->where('status', 'ready')->count(),
                    'out_for_delivery' => $business->orders()->where('status', 'out_for_delivery')->count(),
                    'today' => $business->orders()->whereDate('created_at', today())->count(),
                    'total' => $business->orders_count ?? $business->orders()->count(),
                ],
            ],
            'inventory' => [
                'enabled' => $enabledModules['inventory'] ?? false,
                'products' => $business->products,
                'stats' => [
                    'total' => $business->products_count ?? $business->products()->count(),
                    'in_stock' => $business->products()->where('is_active', true)->count(),
                    'out_of_stock' => $business->products()->where('is_active', false)->count(),
                    'low_stock' => 0, // TODO: add stock tracking
                ],
            ],
        ];

        return response()->json([
            'business' => $business,
            'enabled_modules' => $enabledModules,
            'modules' => $modules,
        ]);
    }

    // Toggle modules for a business
    public function updateModules(Request $request, $id)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'catalog' => 'sometimes|boolean',
            'bookings' => 'sometimes|boolean',
            'orders' => 'sometimes|boolean',
            'inventory' => 'sometimes|boolean',
            'module_config' => 'sometimes|array',
        ]);

        $currentModules = $business->enabled_modules ?? self::getDefaultModules($business->service_type);
        $updatedModules = array_merge($currentModules, $validated);

        // If enabling bookings, ensure services exist
        if (($validated['bookings'] ?? false) && !$business->services()->exists()) {
            return response()->json([
                'message' => 'Cannot enable bookings: no services configured. Add services first.',
            ], 422);
        }

        $business->update([
            'enabled_modules' => $updatedModules,
            'module_config' => $validated['module_config'] ?? $business->module_config,
        ]);

        // Update service_type based on enabled modules
        $business->update(['service_type' => self::determineServiceType($updatedModules)]);

        return response()->json([
            'message' => 'Modules updated.',
            'enabled_modules' => $updatedModules,
            'service_type' => $business->service_type,
        ]);
    }

    protected function getDefaultModules(string $serviceType): array
    {
        return array_merge(self::DEFAULT_MODULES, self::SERVICE_TYPE_MODULES[$serviceType] ?? []);
    }

    protected function determineServiceType(array $modules): string
    {
        if ($modules['orders'] && $modules['bookings']) return 'hybrid';
        if ($modules['orders']) return 'buyable';
        if ($modules['bookings']) return 'bookable';
        return 'directory';
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

    // Services
    public function services(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        $services = $business->services()->orderBy('sort_order')->get();

        return response()->json(compact('services'));
    }

    public function storeService(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:15|max:1440',
            'capacity' => 'nullable|integer|min:1',
            'advance_booking_days' => 'nullable|integer|min:1|max:365',
            'cancellation_hours' => 'nullable|integer|min:0|max:72',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['business_id'] = $business->id;
        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);

        $service = Service::create($validated);

        return response()->json([
            'message' => 'Service added.',
            'service' => $service,
        ]);
    }

    public function updateService(Request $request, $businessId, $serviceId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $service = Service::where('business_id', $business->id)->findOrFail($serviceId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'duration' => 'sometimes|integer|min:15|max:1440',
            'capacity' => 'nullable|integer|min:1',
            'advance_booking_days' => 'nullable|integer|min:1|max:365',
            'cancellation_hours' => 'nullable|integer|min:0|max:72',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $service->update($validated);

        return response()->json([
            'message' => 'Service updated.',
            'service' => $service,
        ]);
    }

    public function destroyService(Request $request, $businessId, $serviceId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $service = Service::where('business_id', $business->id)->findOrFail($serviceId);

        // Check if service has active bookings
        $activeBookings = $service->bookings()->whereIn('status', ['pending', 'confirmed'])->count();
        if ($activeBookings > 0) {
            return response()->json([
                'message' => 'Cannot delete service with active bookings.',
            ], 422);
        }

        $service->delete();

        return response()->json(['message' => 'Service deleted.']);
    }
}
