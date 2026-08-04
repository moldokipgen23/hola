<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DeliveryConfigController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OwnerDashboardController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlatformFeatureController;
use App\Http\Controllers\Api\PincodeController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\PushTokenController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SavedListingController;
use App\Http\Controllers\Api\SearchAnalyticsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TimeSlotController;
use App\Http\Controllers\Api\TransportController;
use App\Http\Controllers\Api\VendorSetupController;
use App\Models\AreaInterest;
use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\World;
use App\Models\WorldHomepageContent;
use App\Services\LaunchControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Public Auth (rate limited) ───
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/google', [AuthController::class, 'googleLogin']);
    Route::post('/auth/otp/send', [AuthController::class, 'sendOtp']);
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/register-owner', [AuthController::class, 'registerOwner']);
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.login');
    Route::post('/auth/admin/login', [AuthController::class, 'adminLogin']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// ─── Public Data ───
Route::get('/platform/features', PlatformFeatureController::class);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/featured', [CategoryController::class, 'featured']);
Route::get('/categories/tree', function () {
    $categories = Category::active()
        ->root()
        ->ordered()
        ->with(['children' => function ($q) {
            $q->active()->ordered()->with(['children' => function ($q2) {
                $q2->active()->ordered();
            }])->withCount('businesses');
        }])
        ->withCount('businesses')
        ->get();

    return response()->json(['data' => $categories]);
});
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/categories/{slug}/businesses', [CategoryController::class, 'showWithBusinesses']);

// Worlds API
Route::get('/worlds', function (LaunchControlService $launchControl) {
    $worlds = World::active()->ordered()->get()
        ->filter(fn (World $world) => $launchControl->worldAvailable($world->slug))
        ->values();

    return response()->json(['data' => $worlds]);
});

Route::get('/worlds/{slug}', function ($slug, LaunchControlService $launchControl) {
    abort_unless($launchControl->worldAvailable($slug), 404);
    $world = World::where('slug', $slug)->firstOrFail();
    $categories = $world->categories()
        ->active()
        ->ordered()
        ->withCount('businesses')
        ->get();

    return response()->json([
        'data' => $world,
        'categories' => $categories,
    ]);
});

Route::get('/worlds/{slug}/categories', function ($slug, LaunchControlService $launchControl) {
    abort_unless($launchControl->worldAvailable($slug), 404);
    $world = World::where('slug', $slug)->firstOrFail();
    $categories = $world->categories()
        ->active()
        ->root()
        ->ordered()
        ->with(['children' => function ($q) {
            $q->active()->ordered()->withCount('businesses');
        }])
        ->withCount('businesses')
        ->get();

    return response()->json(['data' => $categories]);
});

// Category filters API
Route::get('/categories/{id}/filters', function ($id) {
    $category = Category::findOrFail($id);
    $filters = $category->filters()->active()->filterable()->ordered()->get();

    return response()->json(['data' => $filters]);
});

Route::get('/businesses', [BusinessController::class, 'index']);
Route::get('/businesses/featured', [BusinessController::class, 'featured']);
Route::get('/businesses/trending', [BusinessController::class, 'trending']);
Route::get('/businesses/new', [BusinessController::class, 'newlyAdded']);
Route::get('/businesses/nearby', [BusinessController::class, 'nearby']);
Route::get('/businesses/by-category/{slug}', [BusinessController::class, 'byCategory']);
Route::get('/businesses/by-id/{id}', [BusinessController::class, 'showById']);
Route::get('/businesses/{slug}', [BusinessController::class, 'show']);
Route::post('/businesses/{slug}/track', [BusinessController::class, 'trackAction'])
    ->middleware('throttle:30,1');
Route::get('/businesses/{slug}/related', [BusinessController::class, 'related']);
Route::get('/businesses/{slug}/services', [BusinessController::class, 'services'])->middleware('launch:world.book,module.bookings');
Route::get('/businesses/by-id/{id}/services', [BusinessController::class, 'publicServices'])->middleware('launch:world.book,module.bookings');

// Public booking & order
Route::post('/businesses/{slug}/bookings', [PublicBookingController::class, 'storeBooking'])->middleware(['launch:world.book,module.bookings', 'auth.optional', 'throttle:20,1']);
Route::post('/businesses/{slug}/orders', [PublicBookingController::class, 'storeOrder'])->middleware(['launch:world.shop,module.orders', 'auth.optional', 'throttle:20,1']);

// Transport (taxi/vehicle booking)
Route::get('/businesses/{slug}/vehicles', [TransportController::class, 'vehicles'])->middleware('launch:world.ride,module.transport');
Route::post('/businesses/{slug}/trips/estimate', [TransportController::class, 'estimateFare'])->middleware('launch:world.ride,module.transport');
Route::post('/businesses/{slug}/trips', [TransportController::class, 'bookTrip'])->middleware(['launch:world.ride,module.transport', 'auth.optional', 'throttle:20,1']);

// Delivery Zones
Route::get('/businesses/{slug}/delivery-zones', [DeliveryZoneController::class, 'index']);
Route::post('/businesses/{slug}/delivery-check', [DeliveryZoneController::class, 'checkEligibility']);
Route::get('/delivery-zones/check-eligibility', [DeliveryZoneController::class, 'checkEligibility']);

// Time Slots (turf/slot booking)
Route::get('/services/{serviceId}/slots', [TimeSlotController::class, 'slotsByService'])->middleware('launch:world.book,module.bookings,module.turf');

Route::middleware('auth:sanctum')->group(function () {
    // My Bookings & Orders (customer view)
    Route::get('/my-bookings', [CustomerController::class, 'myBookings']);
    Route::get('/my-orders', [CustomerController::class, 'myOrders']);
    Route::put('/my-orders/{id}/cancel', [CustomerController::class, 'cancelOrder']);
    Route::put('/my-bookings/{id}/cancel', [CustomerController::class, 'cancelBooking']);
    Route::post('/my-orders/{id}/reorder', [CustomerController::class, 'reorder']);

    // My Trips (customer view)
    Route::get('/my-trips', [TransportController::class, 'myTrips']);
    Route::put('/my-trips/{id}/cancel', [TransportController::class, 'cancelTrip']);
});

Route::get('/businesses/{business}/reviews', [ReviewController::class, 'index']);

// Homepage content (public)
Route::get('/homepage/{world}', function ($world) {
    $worldModel = World::where('slug', $world)->orWhere('id', $world)->firstOrFail();
    $content = WorldHomepageContent::where('world_id', $worldModel->id)
        ->active()
        ->orderBy('sort_order')
        ->get();

    return response()->json(['data' => $content]);
});

// Delivery config
Route::get('/businesses/{business}/delivery-config', [DeliveryConfigController::class, 'show']);
Route::put('/businesses/{business}/delivery-config', [DeliveryConfigController::class, 'update'])
    ->middleware('auth:sanctum');

// Filters
Route::get('/categories/{category}/filters', [FilterController::class, 'index']);
Route::post('/businesses/filter', [FilterController::class, 'apply']);

// Media
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media/upload', [MediaController::class, 'upload']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);
});

// Public Settings & SEO
Route::get('/settings', [SettingController::class, 'publicSettings']);
Route::get('/sitemap', [SettingController::class, 'sitemap']);

Route::get('/products', [ProductController::class, 'index'])->middleware('launch:world.shop,module.catalog');
Route::get('/products/popular', [ProductController::class, 'popular'])->middleware('launch:world.shop,module.catalog');
Route::get('/products/{slug}', [ProductController::class, 'show'])->middleware('launch:world.shop,module.catalog');

// Public instant search (quick results for search dropdown)
Route::get('/instant-search', function (Request $request) {
    $q = $request->input('q', '');
    $limit = min($request->input('limit', 8), 20);

    if (strlen($q) < 2) {
        return response()->json(['results' => []]);
    }

    $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

    $businesses = Business::active()
        ->with('category:id,name,slug', 'area:id,name,slug')
        ->where(function ($query) use ($safe) {
            $query->where('name', 'like', $safe)
                ->orWhere('address', 'like', $safe)
                ->orWhere('description', 'like', $safe);
        })
        ->orderByDesc('average_rating')
        ->limit($limit)
        ->get()
        ->map(function ($b) {
            $photo = null;
            if (! empty($b->photos) && is_array($b->photos) && count($b->photos) > 0) {
                $p = $b->photos[0];
                $photo = str_starts_with($p, 'http') ? $p : asset($p);
            }

            return [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'address' => $b->address,
                'rating' => $b->average_rating > 0 ? number_format($b->average_rating, 1) : null,
                'category' => $b->category->name ?? null,
                'area' => $b->area->name ?? null,
                'photo' => $photo,
            ];
        });

    return response()->json(['results' => $businesses]);
});

Route::get('/search', [SearchController::class, 'search']);
Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
Route::get('/search/universal', [SearchController::class, 'universal']);

// ─── Authenticated User ───
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    // Email verification
    Route::post('/auth/verify-email/send', [AuthController::class, 'sendVerificationEmail']);
    Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);

    // Saved
    Route::get('/saved', [SavedListingController::class, 'index']);
    Route::post('/saved/toggle', [SavedListingController::class, 'toggle']);
    Route::get('/saved/check', [SavedListingController::class, 'check']);

    // Reports
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/reports/mine', [ReportController::class, 'myReports']);

    // Reviews
    Route::post('/businesses/{business}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);

    // Claims
    Route::post('/claims', [ClaimController::class, 'store']);
    Route::get('/claims/mine', [ClaimController::class, 'myClaims']);

    // Account Management
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/delete-account', [AuthController::class, 'deleteAccount']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Push Tokens
    Route::post('/push-tokens/register', [PushTokenController::class, 'register']);
    Route::post('/push-tokens/unregister', [PushTokenController::class, 'unregister']);
    Route::post('/push-tokens/test', [PushTokenController::class, 'test']);

    // Chat
    Route::get('/chat/conversations', [ChatController::class, 'conversations']);
    Route::get('/chat/conversations/{conversation}', [ChatController::class, 'show']);
    Route::post('/chat/businesses/{business}', [ChatController::class, 'store']);
    Route::post('/chat/conversations/{conversation}/reply', [ChatController::class, 'reply']);

    // Owner Dashboard
    Route::get('/owner/dashboard', [OwnerDashboardController::class, 'dashboard']);
    Route::get('/owner/businesses', [OwnerDashboardController::class, 'businesses']);
    Route::get('/owner/businesses/{id}', [OwnerDashboardController::class, 'showBusiness']);
    Route::put('/owner/businesses/{id}', [OwnerDashboardController::class, 'updateBusiness']);
    Route::post('/owner/businesses/{id}/photos', [OwnerDashboardController::class, 'updatePhotos']);
    Route::delete('/owner/businesses/{id}/photos', [OwnerDashboardController::class, 'deletePhoto']);
    Route::get('/owner/businesses/{id}/analytics', [OwnerDashboardController::class, 'businessAnalytics']);

    // Unified business dashboard with modules
    Route::get('/owner/businesses/{id}/dashboard', [OwnerDashboardController::class, 'businessDashboard']);
    // Module management
    Route::put('/owner/businesses/{id}/modules', [OwnerDashboardController::class, 'updateModules']);

    // Claim Settings (per business)
    Route::get('/owner/businesses/{id}/claim-settings', [OwnerDashboardController::class, 'getClaimSettings']);
    Route::put('/owner/businesses/{id}/claim-settings', [OwnerDashboardController::class, 'updateClaimSettings']);

    // Owner Products
    Route::post('/owner/businesses/{businessId}/products', [OwnerDashboardController::class, 'storeProduct']);
    Route::put('/owner/businesses/{businessId}/products/{productId}', [OwnerDashboardController::class, 'updateProduct']);
    Route::delete('/owner/businesses/{businessId}/products/{productId}', [OwnerDashboardController::class, 'destroyProduct']);

    // Owner Services
    Route::get('/owner/businesses/{businessId}/services', [OwnerDashboardController::class, 'services']);
    Route::post('/owner/businesses/{businessId}/services', [OwnerDashboardController::class, 'storeService']);
    Route::put('/owner/businesses/{businessId}/services/{serviceId}', [OwnerDashboardController::class, 'updateService']);
    Route::delete('/owner/businesses/{businessId}/services/{serviceId}', [OwnerDashboardController::class, 'destroyService']);

    // Owner Bookings
    Route::get('/owner/businesses/{businessId}/bookings', [OwnerDashboardController::class, 'bookings']);
    Route::post('/owner/businesses/{businessId}/bookings', [OwnerDashboardController::class, 'storeBooking']);
    Route::get('/owner/businesses/{businessId}/bookings/{bookingId}', [OwnerDashboardController::class, 'showBooking']);
    Route::put('/owner/businesses/{businessId}/bookings/{bookingId}', [OwnerDashboardController::class, 'updateBooking']);
    Route::put('/owner/businesses/{businessId}/bookings/{bookingId}/status', [OwnerDashboardController::class, 'updateBookingStatus']);
    Route::put('/owner/businesses/{businessId}/bookings/{bookingId}/payment-status', [OwnerDashboardController::class, 'updateBookingPaymentStatus']);
    Route::delete('/owner/businesses/{businessId}/bookings/{bookingId}', [OwnerDashboardController::class, 'destroyBooking']);

    // Owner Orders
    Route::get('/owner/businesses/{businessId}/orders', [OwnerDashboardController::class, 'orders']);
    Route::post('/owner/businesses/{businessId}/orders', [OwnerDashboardController::class, 'storeOrder']);
    Route::get('/owner/businesses/{businessId}/orders/{orderId}', [OwnerDashboardController::class, 'showOrder']);
    Route::put('/owner/businesses/{businessId}/orders/{orderId}/status', [OwnerDashboardController::class, 'updateOrderStatus']);
    Route::put('/owner/businesses/{businessId}/orders/{orderId}/payment-status', [OwnerDashboardController::class, 'updateOrderPaymentStatus']);
    Route::delete('/owner/businesses/{businessId}/orders/{orderId}', [OwnerDashboardController::class, 'destroyOrder']);

    // Owner Vehicles
    Route::get('/owner/businesses/{businessId}/vehicles', [OwnerDashboardController::class, 'vehicles']);
    Route::post('/owner/businesses/{businessId}/vehicles', [OwnerDashboardController::class, 'storeVehicle']);
    Route::put('/owner/businesses/{businessId}/vehicles/{vehicleId}', [OwnerDashboardController::class, 'updateVehicle']);
    Route::delete('/owner/businesses/{businessId}/vehicles/{vehicleId}', [OwnerDashboardController::class, 'destroyVehicle']);

    // Owner Trips
    Route::get('/owner/businesses/{businessId}/trips', [OwnerDashboardController::class, 'trips']);
    Route::get('/owner/businesses/{businessId}/trips/{tripId}', [OwnerDashboardController::class, 'showTrip']);
    Route::put('/owner/businesses/{businessId}/trips/{tripId}/status', [OwnerDashboardController::class, 'updateTripStatus']);
    Route::put('/owner/businesses/{businessId}/trips/{tripId}/quote', [OwnerDashboardController::class, 'updateTripQuote']);
    Route::put('/owner/businesses/{businessId}/trips/{tripId}/payment-status', [OwnerDashboardController::class, 'updateTripPaymentStatus']);

    // Owner Time Slots
    Route::get('/owner/businesses/{businessId}/services/{serviceId}/slots', [OwnerDashboardController::class, 'timeSlots']);
    Route::post('/owner/businesses/{businessId}/services/{serviceId}/slots', [OwnerDashboardController::class, 'storeTimeSlot']);
    Route::put('/owner/businesses/{businessId}/services/{serviceId}/slots/{slotId}', [OwnerDashboardController::class, 'updateTimeSlot']);
    Route::delete('/owner/businesses/{businessId}/services/{serviceId}/slots/{slotId}', [OwnerDashboardController::class, 'destroyTimeSlot']);

    // Owner Delivery Zones
    Route::get('/owner/businesses/{businessId}/delivery-zones', [OwnerDashboardController::class, 'deliveryZones']);
    Route::post('/owner/businesses/{businessId}/delivery-zones', [OwnerDashboardController::class, 'storeDeliveryZone']);
    Route::put('/owner/businesses/{businessId}/delivery-zones/{zoneId}', [OwnerDashboardController::class, 'updateDeliveryZone']);
    Route::delete('/owner/businesses/{businessId}/delivery-zones/{zoneId}', [OwnerDashboardController::class, 'destroyDeliveryZone']);

    // Owner Reviews
    Route::post('/owner/reviews/{reviewId}/respond', [OwnerDashboardController::class, 'respondToReview']);

    // Search Analytics
    Route::post('/search/track', [SearchAnalyticsController::class, 'track']);
    Route::post('/search/click', [SearchAnalyticsController::class, 'click']);

    // Vendor Setup
    Route::get('/vendor/setup', [VendorSetupController::class, 'show']);
    Route::put('/vendor/setup', [VendorSetupController::class, 'update']);
    Route::get('/vendor/setup/progress', [VendorSetupController::class, 'progress']);
});

// ─── Admin Routes ───
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::post('/businesses', [AdminController::class, 'storeBusiness']);
    Route::put('/businesses/{id}', [AdminController::class, 'updateBusiness']);
    Route::delete('/businesses/{id}', [AdminController::class, 'destroyBusiness']);
    Route::patch('/businesses/{id}/toggle', [AdminController::class, 'toggleBusiness']);
    Route::patch('/businesses/{id}/verify', [AdminController::class, 'verifyBusiness']);

    Route::post('/categories', [AdminController::class, 'storeCategory']);
    Route::put('/categories/{id}', [AdminController::class, 'updateCategory']);
    Route::delete('/categories/{id}', [AdminController::class, 'destroyCategory']);

    Route::post('/subcategories', [AdminController::class, 'storeSubcategory']);
    Route::put('/subcategories/{id}', [AdminController::class, 'updateSubcategory']);
    Route::delete('/subcategories/{id}', [AdminController::class, 'destroySubcategory']);

    Route::post('/products', [AdminController::class, 'storeProduct']);
    Route::put('/products/{id}', [AdminController::class, 'updateProduct']);
    Route::delete('/products/{id}', [AdminController::class, 'destroyProduct']);

    Route::put('/reports/{id}', [AdminController::class, 'updateReport']);

    Route::put('/claims/{id}', [ClaimController::class, 'update']);

    Route::put('/settings', [SettingController::class, 'update']);

    // Admin reviews management
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
});

// ─── Pincode Master Data ───
Route::get('/pincodes/lookup', [PincodeController::class, 'lookup']);
Route::get('/pincodes/search', [PincodeController::class, 'search']);
Route::get('/pincodes/nearby', [PincodeController::class, 'nearby']);

// ─── Area Interest / Coming Soon ───
Route::post('/area-interest', function (Request $request) {
    $request->validate([
        'pincode' => 'required|string|size:6',
        'phone' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255',
    ]);

    $pincode = Pincode::lookup($request->pincode);

    AreaInterest::create([
        'pincode' => $request->pincode,
        'locality' => $pincode?->locality,
        'district' => $pincode?->district,
        'state' => $pincode?->state,
        'phone' => $request->phone,
        'email' => $request->email,
    ]);

    $areaName = $pincode ? "{$pincode->district}, {$pincode->state}" : 'your area';

    return response()->json([
        'message' => "Thanks! We'll notify you when we launch in {$areaName}.",
    ]);
});

// ─── Payments (auth required) ───
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payments/config', [PaymentController::class, 'config']);
    Route::post('/payments/create-order', [PaymentController::class, 'createOrder']);
    Route::post('/payments/verify', [PaymentController::class, 'verifyPayment']);
});
