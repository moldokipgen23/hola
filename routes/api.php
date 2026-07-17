<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OwnerDashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SavedListingController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TimeSlotController;
use App\Http\Controllers\Api\TransportController;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Public Auth (rate limited) ───
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/google', [AuthController::class, 'googleLogin']);
    Route::post('/auth/otp/send', [AuthController::class, 'sendOtp']);
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/register-owner', [AuthController::class, 'registerOwner']);
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
    Route::post('/auth/admin/login', [AuthController::class, 'adminLogin']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// ─── Public Data ───
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/featured', [CategoryController::class, 'featured']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/categories/{slug}/businesses', [CategoryController::class, 'showWithBusinesses']);

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
Route::get('/businesses/{slug}/services', [BusinessController::class, 'services']);
Route::get('/businesses/by-id/{id}/services', [BusinessController::class, 'publicServices']);

// Public booking & order
Route::post('/businesses/{slug}/bookings', [PublicBookingController::class, 'storeBooking']);
Route::post('/businesses/{slug}/orders', [PublicBookingController::class, 'storeOrder']);

// Transport (taxi/vehicle booking)
Route::get('/businesses/{slug}/vehicles', [TransportController::class, 'vehicles']);
Route::post('/businesses/{slug}/trips/estimate', [TransportController::class, 'estimateFare']);
Route::post('/businesses/{slug}/trips', [TransportController::class, 'bookTrip']);

// Delivery Zones
Route::get('/businesses/{slug}/delivery-zones', [DeliveryZoneController::class, 'index']);
Route::post('/businesses/{slug}/delivery-check', [DeliveryZoneController::class, 'checkEligibility']);
Route::get('/delivery-zones/check-eligibility', [DeliveryZoneController::class, 'checkEligibility']);

// Time Slots (turf/slot booking)
Route::get('/services/{serviceId}/slots', [TimeSlotController::class, 'slotsByService']);

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

// Public Settings & SEO
Route::get('/settings', [SettingController::class, 'publicSettings']);
Route::get('/sitemap', [SettingController::class, 'sitemap']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/popular', [ProductController::class, 'popular']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Public instant search (quick results for search dropdown)
Route::get('/instant-search', function (Request $request) {
    $q = $request->input('q', '');
    $limit = min($request->input('limit', 8), 20);

    if (strlen($q) < 2) {
        return response()->json(['results' => []]);
    }

    $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

    $businesses = Business::active()
        ->inServiceableArea()
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
    Route::delete('/owner/businesses/{businessId}/bookings/{bookingId}', [OwnerDashboardController::class, 'destroyBooking']);

    // Owner Orders
    Route::get('/owner/businesses/{businessId}/orders', [OwnerDashboardController::class, 'orders']);
    Route::post('/owner/businesses/{businessId}/orders', [OwnerDashboardController::class, 'storeOrder']);
    Route::get('/owner/businesses/{businessId}/orders/{orderId}', [OwnerDashboardController::class, 'showOrder']);
    Route::put('/owner/businesses/{businessId}/orders/{orderId}/status', [OwnerDashboardController::class, 'updateOrderStatus']);
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

    Route::get('/reports', [AdminController::class, 'indexReports']);
    Route::put('/reports/{id}', [AdminController::class, 'updateReport']);

    Route::get('/claims', [ClaimController::class, 'index']);
    Route::put('/claims/{id}', [ClaimController::class, 'update']);

    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);

    Route::get('/analytics', [AdminController::class, 'analytics']);

    // Admin reviews management
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
});

// ─── Pincode Master Data ───
Route::get('/pincodes/lookup', [\App\Http\Controllers\Api\PincodeController::class, 'lookup']);
Route::get('/pincodes/search', [\App\Http\Controllers\Api\PincodeController::class, 'search']);
Route::get('/pincodes/nearby', [\App\Http\Controllers\Api\PincodeController::class, 'nearby']);

// ─── Area Interest / Coming Soon ───
Route::post('/area-interest', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'pincode' => 'required|string|size:6',
        'phone' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255',
    ]);

    $pincode = \App\Models\Pincode::lookup($request->pincode);

    \App\Models\AreaInterest::create([
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

// ─── Payments (public — uses JWT) ───
Route::get('/payments/config', [\App\Http\Controllers\Api\PaymentController::class, 'config']);
Route::post('/payments/create-order', [\App\Http\Controllers\Api\PaymentController::class, 'createOrder']);
Route::post('/payments/verify', [\App\Http\Controllers\Api\PaymentController::class, 'verifyPayment']);
