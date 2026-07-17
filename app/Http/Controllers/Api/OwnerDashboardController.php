<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\Trip;
use App\Models\Vehicle;
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
        'transport' => false,
        'turf' => false,
    ];

    // Module defaults by service_type
    protected const SERVICE_TYPE_MODULES = [
        'directory' => ['catalog' => true],
        'bookable' => ['catalog' => true, 'bookings' => true],
        'buyable' => ['catalog' => true, 'orders' => true, 'inventory' => true],
        'hybrid' => ['catalog' => true, 'bookings' => true, 'orders' => true],
        'transport' => ['catalog' => true, 'transport' => true],
        'turf' => ['catalog' => true, 'turf' => true],
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

        $recentConversations = Conversation::whereIn('business_id', $businesses->pluck('id'))
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
                'products' => fn ($q) => $q->latest()->take(10),
                'services' => fn ($q) => $q->orderBy('sort_order'),
                'bookings' => fn ($q) => $q->where('status', 'pending')->where('booking_date', '>=', now()->toDateString())->latest('booking_date')->take(10),
                'orders' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery'])->latest()->take(10),
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
            'transport' => 'sometimes|boolean',
            'turf' => 'sometimes|boolean',
            'module_config' => 'sometimes|array',
        ]);

        $currentModules = $business->enabled_modules ?? self::getDefaultModules($business->service_type);
        $updatedModules = array_merge($currentModules, $validated);

        // If enabling bookings, ensure services exist
        if (($validated['bookings'] ?? false) && ! $business->services()->exists()) {
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
        if (($modules['transport'] ?? false)) {
            return 'transport';
        }
        if (($modules['turf'] ?? false)) {
            return 'turf';
        }
        if ($modules['orders'] && $modules['bookings']) {
            return 'hybrid';
        }
        if ($modules['orders']) {
            return 'buyable';
        }
        if ($modules['bookings']) {
            return 'bookable';
        }

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
            ->with(['category', 'products', 'reviews' => fn ($q) => $q->with('user:id,name')->latest()])
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
            'pincode' => 'sometimes|required|string|size:6',
            'delivery_radius_km' => 'nullable|numeric|min:1|max:100',
            'working_hours' => 'nullable|array',
        ]);

        // Handle pincode update
        if ($request->has('pincode')) {
            $pincode = Pincode::lookup($validated['pincode']);
            if (! $pincode) {
                return response()->json(['message' => 'Invalid pincode.'], 422);
            }
            if (! $pincode->serviceable) {
                return response()->json([
                    'message' => "We're not in {$pincode->district}, {$pincode->state} yet. This area is not serviceable.",
                ], 422);
            }
            $validated['state'] = $pincode->state;
            $validated['district'] = $pincode->district;
        }

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
            $filename = 'businesses/'.$business->slug.'_'.Str::random(6).'.'.$file->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($file));
            $photos[] = 'storage/'.$filename;
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
        $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);

        if ($request->hasFile('image')) {
            $filename = 'products/'.$validated['slug'].'.'.$request->file('image')->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($request->file('image')));
            $validated['image'] = 'storage/'.$filename;
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
            $filename = 'products/'.$product->slug.'.'.$request->file('image')->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($request->file('image')));
            $validated['image'] = 'storage/'.$filename;
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
        $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);

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

    // Bookings
    public function bookings(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        $query = $business->bookings()->with('service')->orderByDesc('booking_date');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date')) {
            $query->where('booking_date', $request->date);
        }
        if ($request->has('upcoming')) {
            $query->where('booking_date', '>=', now()->toDateString())
                ->whereIn('status', ['pending', 'confirmed']);
        }

        $bookings = $query->paginate($request->get('per_page', 20));

        return response()->json($bookings);
    }

    public function storeBooking(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        // Verify service belongs to business
        $service = Service::where('business_id', $business->id)->findOrFail($request->service_id);

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'booking_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'duration_minutes' => 'required|integer|min:15',
            'total_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['business_id'] = $business->id;
        $validated['status'] = 'pending';

        $booking = Booking::create($validated);

        // Load service relationship
        $booking->load('service');

        return response()->json([
            'message' => 'Booking created.',
            'booking' => $booking,
        ], 201);
    }

    public function showBooking(Request $request, $businessId, $bookingId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $booking = $business->bookings()->with('service')->findOrFail($bookingId);

        return response()->json(compact('booking'));
    }

    public function updateBooking(Request $request, $businessId, $bookingId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $booking = $business->bookings()->findOrFail($bookingId);

        $validated = $request->validate([
            'customer_name' => 'sometimes|string|max:255',
            'customer_phone' => 'sometimes|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'booking_date' => 'sometimes|date',
            'start_time' => 'sometimes',
            'end_time' => 'sometimes',
            'duration_minutes' => 'sometimes|integer|min:15',
            'total_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $booking->update($validated);
        $booking->load('service');

        return response()->json([
            'message' => 'Booking updated.',
            'booking' => $booking,
        ]);
    }

    public function updateBookingStatus(Request $request, $businessId, $bookingId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $booking = $business->bookings()->findOrFail($bookingId);

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed,no_show',
            'cancellation_reason' => 'nullable|string',
        ]);

        $oldStatus = $booking->status;
        $booking->update($validated);

        // Set timestamps based on status
        if ($validated['status'] === 'confirmed' && $oldStatus !== 'confirmed') {
            $booking->update(['confirmed_at' => now()]);
        } elseif ($validated['status'] === 'completed' && $oldStatus !== 'completed') {
            $booking->update(['completed_at' => now()]);
        } elseif ($validated['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
            $booking->update(['cancelled_at' => now()]);
        }

        return response()->json([
            'message' => 'Status updated.',
            'booking' => $booking->fresh(),
        ]);
    }

    public function destroyBooking(Request $request, $businessId, $bookingId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $booking = $business->bookings()->findOrFail($bookingId);

        if (in_array($booking->status, ['confirmed', 'completed'])) {
            return response()->json([
                'message' => 'Cannot delete confirmed or completed booking.',
            ], 422);
        }

        $booking->delete();

        return response()->json(['message' => 'Booking deleted.']);
    }

    // ─── Owner Orders CRUD ───

    public function orders(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        $query = $business->orders()->with('items');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $orders = $query->paginate($request->get('per_page', 20));

        return response()->json($orders);
    }

    public function showOrder(Request $request, $businessId, $orderId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $order = $business->orders()->with('items')->findOrFail($orderId);

        return response()->json(compact('order'));
    }

    public function storeOrder(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'delivery_address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $productIds = collect($validated['items'])->pluck('product_id');
        $products = Product::where('business_id', $business->id)
            ->whereIn('id', $productIds)
            ->get();

        if ($products->count() !== $productIds->count()) {
            return response()->json(['message' => 'Invalid products in order.'], 422);
        }

        $orderNumber = 'ORD-'.strtoupper(Str::random(8));

        $orderData = [
            'business_id' => $business->id,
            'order_number' => $orderNumber,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'delivery_address' => $validated['delivery_address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 0,
            'total' => 0,
            'user_id' => $request->user()->id,
        ];

        $order = Order::create($orderData);

        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $product = $products->find($item['product_id']);

            if ($product->stock !== null && $product->stock < $item['quantity']) {
                $order->delete();

                return response()->json([
                    'message' => "Insufficient stock for {$product->name}. Available: {$product->stock}",
                ], 422);
            }

            $totalPrice = $product->price * $item['quantity'];
            $subtotal += $totalPrice;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
                'total_price' => $totalPrice,
            ]);

            if ($product->stock !== null) {
                $product->decrement('stock', $item['quantity']);
            }
        }

        $order->update([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);

        $order->load('items');

        return response()->json([
            'message' => 'Order created.',
            'order' => $order,
        ], 201);
    }

    protected const VALID_ORDER_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function updateOrderStatus(Request $request, $businessId, $orderId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $order = $business->orders()->findOrFail($orderId);

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready,out_for_delivery,delivered,cancelled',
            'cancellation_reason' => 'nullable|string',
        ]);

        $allowed = self::VALID_ORDER_TRANSITIONS[$order->status] ?? [];
        if (! in_array($validated['status'], $allowed)) {
            return response()->json([
                'message' => "Cannot transition from '{$order->status}' to '{$validated['status']}'.",
            ], 422);
        }

        $order->update($validated);

        $statusTimestamps = [
            'confirmed' => 'confirmed_at',
            'ready' => 'ready_at',
            'delivered' => 'delivered_at',
            'cancelled' => 'cancelled_at',
        ];

        if (isset($statusTimestamps[$validated['status']])) {
            $order->update([$statusTimestamps[$validated['status']] => now()]);
        }

        return response()->json([
            'message' => 'Order status updated.',
            'order' => $order->fresh()->load('items'),
        ]);
    }

    public function destroyOrder(Request $request, $businessId, $orderId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $order = $business->orders()->findOrFail($orderId);

        if (in_array($order->status, ['confirmed', 'preparing', 'out_for_delivery', 'delivered'])) {
            return response()->json([
                'message' => 'Cannot delete an active or completed order.',
            ], 422);
        }

        $order->items()->delete();
        $order->delete();

        return response()->json(['message' => 'Order deleted.']);
    }

    // ─── Vehicle CRUD ───

    public function vehicles(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $vehicles = $business->vehicles()->orderBy('sort_order')->get();

        return response()->json(compact('vehicles'));
    }

    public function storeVehicle(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:car,bolero,suv,van,auto,bike',
            'seats' => 'required|integer|min:1|max:50',
            'base_fare' => 'required|numeric|min:0',
            'fare_per_km' => 'required|numeric|min:0',
            'min_km' => 'nullable|integer|min:1',
            'registration_number' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['business_id'] = $business->id;
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        if ($request->hasFile('image')) {
            $filename = 'vehicles/'.Str::slug($validated['name']).'-'.Str::random(6).'.'.$request->file('image')->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($request->file('image')));
            $validated['image'] = 'storage/'.$filename;
        }

        $vehicle = Vehicle::create($validated);

        return response()->json([
            'message' => 'Vehicle added.',
            'vehicle' => $vehicle,
        ], 201);
    }

    public function updateVehicle(Request $request, $businessId, $vehicleId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $vehicle = Vehicle::where('business_id', $business->id)->findOrFail($vehicleId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:car,bolero,suv,van,auto,bike',
            'seats' => 'sometimes|integer|min:1|max:50',
            'base_fare' => 'sometimes|numeric|min:0',
            'fare_per_km' => 'sometimes|numeric|min:0',
            'min_km' => 'nullable|integer|min:1',
            'registration_number' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $vehicle->update($validated);

        return response()->json([
            'message' => 'Vehicle updated.',
            'vehicle' => $vehicle->fresh(),
        ]);
    }

    public function destroyVehicle($businessId, $vehicleId)
    {
        $business = Business::where('created_by', request()->user()->id)->findOrFail($businessId);
        $vehicle = Vehicle::where('business_id', $business->id)->findOrFail($vehicleId);

        if ($vehicle->trips()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return response()->json(['message' => 'Cannot delete vehicle with active trips.'], 422);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted.']);
    }

    // ─── Trip Management ───

    public function trips(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        $query = $business->trips()->with('vehicle');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date')) {
            $query->where('trip_date', $request->date);
        }

        $trips = $query->paginate($request->get('per_page', 20));

        return response()->json($trips);
    }

    public function showTrip(Request $request, $businessId, $tripId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $trip = $business->trips()->with('vehicle')->findOrFail($tripId);

        return response()->json(compact('trip'));
    }

    public function updateTripStatus(Request $request, $businessId, $tripId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $trip = $business->trips()->findOrFail($tripId);

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,started,completed,cancelled',
            'driver_name' => 'nullable|string|max:255',
            'driver_phone' => 'nullable|string|max:20',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $trip->update($validated);

        if ($validated['status'] === 'confirmed') {
            $trip->update(['booked_at' => now()]);
        } elseif ($validated['status'] === 'started') {
            $trip->update(['started_at' => now()]);
        } elseif ($validated['status'] === 'completed') {
            $trip->update(['completed_at' => now()]);
        } elseif ($validated['status'] === 'cancelled') {
            $trip->update(['cancelled_at' => now()]);
        }

        return response()->json([
            'message' => 'Trip status updated.',
            'trip' => $trip->fresh()->load('vehicle'),
        ]);
    }

    // ─── Time Slot CRUD ───

    public function timeSlots(Request $request, $businessId, $serviceId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $service = Service::where('business_id', $business->id)->findOrFail($serviceId);

        $slots = $service->timeSlots()->orderBy('day_of_week')->orderBy('start_time')->get();

        return response()->json(compact('slots'));
    }

    public function storeTimeSlot(Request $request, $businessId, $serviceId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $service = Service::where('business_id', $business->id)->findOrFail($serviceId);

        $validated = $request->validate([
            'day_of_week' => 'nullable|integer|between:0,6',
            'start_time' => 'required',
            'end_time' => 'required',
            'capacity' => 'required|integer|min:1',
            'price_override' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['service_id'] = $service->id;
        $slot = TimeSlot::create($validated);

        return response()->json([
            'message' => 'Time slot added.',
            'slot' => $slot,
        ], 201);
    }

    public function updateTimeSlot(Request $request, $businessId, $serviceId, $slotId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
        $slot = TimeSlot::where('service_id', $service->id)->findOrFail($slotId);

        $validated = $request->validate([
            'day_of_week' => 'nullable|integer|between:0,6',
            'start_time' => 'sometimes',
            'end_time' => 'sometimes',
            'capacity' => 'sometimes|integer|min:1',
            'price_override' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $slot->update($validated);

        return response()->json([
            'message' => 'Time slot updated.',
            'slot' => $slot->fresh(),
        ]);
    }

    public function destroyTimeSlot($businessId, $serviceId, $slotId)
    {
        $business = Business::where('created_by', request()->user()->id)->findOrFail($businessId);
        $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
        $slot = TimeSlot::where('service_id', $service->id)->findOrFail($slotId);

        if ($slot->bookings()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return response()->json(['message' => 'Cannot delete slot with active bookings.'], 422);
        }

        $slot->delete();

        return response()->json(['message' => 'Time slot deleted.']);
    }

    // ─── Delivery Zone CRUD ───

    public function deliveryZones(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $zones = $business->deliveryZones()->with('area:id,name,slug')->get();

        return response()->json(compact('zones'));
    }

    public function storeDeliveryZone(Request $request, $businessId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);

        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
            'min_order_amount' => 'nullable|numeric|min:0',
            'delivery_fee' => 'required|numeric|min:0',
            'estimated_minutes' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $exists = $business->deliveryZones()->where('area_id', $validated['area_id'])->exists();
        if ($exists) {
            return response()->json(['message' => 'Delivery zone for this area already exists.'], 422);
        }

        $validated['business_id'] = $business->id;
        $zone = DeliveryZone::create($validated);

        return response()->json([
            'message' => 'Delivery zone added.',
            'zone' => $zone->load('area:id,name,slug'),
        ], 201);
    }

    public function updateDeliveryZone(Request $request, $businessId, $zoneId)
    {
        $business = Business::where('created_by', $request->user()->id)->findOrFail($businessId);
        $zone = DeliveryZone::where('business_id', $business->id)->findOrFail($zoneId);

        $validated = $request->validate([
            'min_order_amount' => 'nullable|numeric|min:0',
            'delivery_fee' => 'sometimes|numeric|min:0',
            'estimated_minutes' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $zone->update($validated);

        return response()->json([
            'message' => 'Delivery zone updated.',
            'zone' => $zone->fresh()->load('area:id,name,slug'),
        ]);
    }

    public function destroyDeliveryZone($businessId, $zoneId)
    {
        $business = Business::where('created_by', request()->user()->id)->findOrFail($businessId);
        $zone = DeliveryZone::where('business_id', $business->id)->findOrFail($zoneId);
        $zone->delete();

        return response()->json(['message' => 'Delivery zone deleted.']);
    }
}
