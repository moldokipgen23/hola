<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OwnerDashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SavedListingController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ClaimController;
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

Route::get('/businesses/{business}/reviews', [ReviewController::class, 'index']);

// Public Settings & SEO
Route::get('/settings', [SettingController::class, 'publicSettings']);
Route::get('/sitemap', [SettingController::class, 'sitemap']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/popular', [ProductController::class, 'popular']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Public instant search
Route::get('/search', function (\Illuminate\Http\Request $request) {
    $q = $request->input('q', '');
    $limit = min($request->input('limit', 8), 20);

    if (strlen($q) < 2) {
        return response()->json(['results' => []]);
    }

    $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

    $businesses = \App\Models\Business::active()
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
            if (!empty($b->photos) && is_array($b->photos) && count($b->photos) > 0) {
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

    // Owner Products
    Route::post('/owner/businesses/{businessId}/products', [OwnerDashboardController::class, 'storeProduct']);
    Route::put('/owner/businesses/{businessId}/products/{productId}', [OwnerDashboardController::class, 'updateProduct']);
    Route::delete('/owner/businesses/{businessId}/products/{productId}', [OwnerDashboardController::class, 'destroyProduct']);

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
