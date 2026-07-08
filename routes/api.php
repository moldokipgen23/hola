<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavedListingController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

// ─── Public Auth (rate limited) ───
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/google', [AuthController::class, 'googleLogin']);
    Route::post('/auth/otp/send', [AuthController::class, 'sendOtp']);
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
    Route::post('/auth/admin/login', [AuthController::class, 'adminLogin']);
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

// Public Settings & SEO
Route::get('/settings', [\App\Http\Controllers\Api\SettingController::class, 'publicSettings']);
Route::get('/sitemap', [\App\Http\Controllers\Api\SettingController::class, 'sitemap']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/popular', [ProductController::class, 'popular']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

Route::get('/search', [SearchController::class, 'search']);
Route::get('/search/suggestions', [SearchController::class, 'suggestions']);

// ─── Authenticated User ───
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    Route::get('/saved', [SavedListingController::class, 'index']);
    Route::post('/saved/toggle', [SavedListingController::class, 'toggle']);
    Route::get('/saved/check', [SavedListingController::class, 'check']);

    Route::post('/reports', [ReportController::class, 'store']);

    Route::post('/claims', [\App\Http\Controllers\Api\ClaimController::class, 'store']);
    Route::get('/claims/mine', [\App\Http\Controllers\Api\ClaimController::class, 'myClaims']);
});

// ─── Admin Routes ───
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Businesses
    Route::post('/businesses', [AdminController::class, 'storeBusiness']);
    Route::put('/businesses/{id}', [AdminController::class, 'updateBusiness']);
    Route::delete('/businesses/{id}', [AdminController::class, 'destroyBusiness']);
    Route::patch('/businesses/{id}/toggle', [AdminController::class, 'toggleBusiness']);
    Route::patch('/businesses/{id}/verify', [AdminController::class, 'verifyBusiness']);

    // Categories
    Route::post('/categories', [AdminController::class, 'storeCategory']);
    Route::put('/categories/{id}', [AdminController::class, 'updateCategory']);
    Route::delete('/categories/{id}', [AdminController::class, 'destroyCategory']);

    // Subcategories
    Route::post('/subcategories', [AdminController::class, 'storeSubcategory']);
    Route::put('/subcategories/{id}', [AdminController::class, 'updateSubcategory']);
    Route::delete('/subcategories/{id}', [AdminController::class, 'destroySubcategory']);

    // Products
    Route::post('/products', [AdminController::class, 'storeProduct']);
    Route::put('/products/{id}', [AdminController::class, 'updateProduct']);
    Route::delete('/products/{id}', [AdminController::class, 'destroyProduct']);

    // Reports
    Route::get('/reports', [AdminController::class, 'indexReports']);
    Route::put('/reports/{id}', [AdminController::class, 'updateReport']);

    // Claims
    Route::get('/claims', [\App\Http\Controllers\Api\ClaimController::class, 'index']);
    Route::put('/claims/{id}', [\App\Http\Controllers\Api\ClaimController::class, 'update']);

    // Settings
    Route::get('/settings', [\App\Http\Controllers\Api\SettingController::class, 'index']);
    Route::put('/settings', [\App\Http\Controllers\Api\SettingController::class, 'update']);

    // Analytics
    Route::get('/analytics', [AdminController::class, 'analytics']);
});
