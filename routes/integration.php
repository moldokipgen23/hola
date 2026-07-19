<?php

use App\Http\Controllers\Integration\AuditLogController;
use App\Http\Controllers\Integration\BookingController;
use App\Http\Controllers\Integration\BusinessController;
use App\Http\Controllers\Integration\CustomerController;
use App\Http\Controllers\Integration\KeyController;
use App\Http\Controllers\Integration\LeadController;
use App\Http\Controllers\Integration\OpenApiController;
use App\Http\Controllers\Integration\OrderController;
use App\Http\Controllers\Integration\ProductController;
use App\Http\Controllers\Integration\ServiceController;
use App\Http\Controllers\Integration\WebhookSubscriptionController;
use App\Models\Scopes;
use Illuminate\Support\Facades\Route;

Route::get('/openapi', [OpenApiController::class, 'spec']);

Route::middleware(['auth.integration', 'throttle:200,1'])->group(function () {

    // API Key management (admin scope only)
    Route::middleware('integration.scope:' . Scopes::ADMIN)->prefix('api-keys')->group(function () {
        Route::get('/', [KeyController::class, 'index']);
        Route::post('/', [KeyController::class, 'store']);
        Route::get('/{id}', [KeyController::class, 'show']);
        Route::patch('/{id}', [KeyController::class, 'update']);
        Route::delete('/{id}', [KeyController::class, 'revoke']);
        Route::post('/{id}/rotate', [KeyController::class, 'rotate']);
    });

    // Businesses
    Route::prefix('businesses')->group(function () {
        Route::get('/', [BusinessController::class, 'index'])->middleware('integration.scope:' . Scopes::BUSINESSES_READ);
        Route::get('/{id}', [BusinessController::class, 'show'])->middleware('integration.scope:' . Scopes::BUSINESSES_READ);
        Route::patch('/{id}', [BusinessController::class, 'update'])->middleware('integration.scope:' . Scopes::BUSINESSES_WRITE);
    });

    // Products
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->middleware('integration.scope:' . Scopes::PRODUCTS_READ);
        Route::get('/{id}', [ProductController::class, 'show'])->middleware('integration.scope:' . Scopes::PRODUCTS_READ);
        Route::post('/', [ProductController::class, 'store'])->middleware('integration.scope:' . Scopes::PRODUCTS_WRITE);
        Route::patch('/{id}', [ProductController::class, 'update'])->middleware('integration.scope:' . Scopes::PRODUCTS_WRITE);
        Route::delete('/{id}', [ProductController::class, 'destroy'])->middleware('integration.scope:' . Scopes::PRODUCTS_WRITE);
    });

    // Services
    Route::prefix('services')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])->middleware('integration.scope:' . Scopes::SERVICES_READ);
        Route::get('/{id}', [ServiceController::class, 'show'])->middleware('integration.scope:' . Scopes::SERVICES_READ);
        Route::post('/', [ServiceController::class, 'store'])->middleware('integration.scope:' . Scopes::SERVICES_WRITE);
        Route::patch('/{id}', [ServiceController::class, 'update'])->middleware('integration.scope:' . Scopes::SERVICES_WRITE);
        Route::delete('/{id}', [ServiceController::class, 'destroy'])->middleware('integration.scope:' . Scopes::SERVICES_WRITE);
    });

    // Customers
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->middleware('integration.scope:' . Scopes::CUSTOMERS_READ);
        Route::get('/{id}', [CustomerController::class, 'show'])->middleware('integration.scope:' . Scopes::CUSTOMERS_READ);
        Route::patch('/{id}', [CustomerController::class, 'update'])->middleware('integration.scope:' . Scopes::CUSTOMERS_WRITE);
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('integration.scope:' . Scopes::ORDERS_READ);
        Route::get('/{id}', [OrderController::class, 'show'])->middleware('integration.scope:' . Scopes::ORDERS_READ);
        Route::patch('/{id}/status', [OrderController::class, 'updateStatus'])->middleware('integration.scope:' . Scopes::ORDERS_WRITE);
    });

    // Bookings
    Route::prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->middleware('integration.scope:' . Scopes::BOOKINGS_READ);
        Route::get('/{id}', [BookingController::class, 'show'])->middleware('integration.scope:' . Scopes::BOOKINGS_READ);
        Route::patch('/{id}/status', [BookingController::class, 'updateStatus'])->middleware('integration.scope:' . Scopes::BOOKINGS_WRITE);
    });

    // Leads
    Route::prefix('leads')->group(function () {
        Route::get('/', [LeadController::class, 'index'])->middleware('integration.scope:' . Scopes::LEADS_READ);
        Route::get('/{id}', [LeadController::class, 'show'])->middleware('integration.scope:' . Scopes::LEADS_READ);
        Route::patch('/{id}/score', [LeadController::class, 'updateLeadScore'])->middleware('integration.scope:' . Scopes::LEADS_WRITE);
    });

    // Webhooks
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookSubscriptionController::class, 'index'])->middleware('integration.scope:' . Scopes::WEBHOOKS_READ);
        Route::post('/', [WebhookSubscriptionController::class, 'store'])->middleware('integration.scope:' . Scopes::WEBHOOKS_WRITE);
        Route::get('/{id}', [WebhookSubscriptionController::class, 'show'])->middleware('integration.scope:' . Scopes::WEBHOOKS_READ);
        Route::patch('/{id}', [WebhookSubscriptionController::class, 'update'])->middleware('integration.scope:' . Scopes::WEBHOOKS_WRITE);
        Route::delete('/{id}', [WebhookSubscriptionController::class, 'destroy'])->middleware('integration.scope:' . Scopes::WEBHOOKS_WRITE);
        Route::get('/{id}/deliveries', [WebhookSubscriptionController::class, 'deliveries'])->middleware('integration.scope:' . Scopes::WEBHOOKS_READ);
        Route::post('/deliveries/{id}/retry', [WebhookSubscriptionController::class, 'retry'])->middleware('integration.scope:' . Scopes::WEBHOOKS_WRITE);
    });

    // Audit logs
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->middleware('integration.scope:' . Scopes::ADMIN);
        Route::get('/{id}', [AuditLogController::class, 'show'])->middleware('integration.scope:' . Scopes::ADMIN);
    });
});
