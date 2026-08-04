<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthenticateIntegrationRequest;
use App\Http\Middleware\EnsureVendorOwnership;
use App\Http\Middleware\IntegrationScope;
use App\Http\Middleware\IntegrationTenant;
use App\Http\Middleware\OptionalSanctumAuthentication;
use App\Http\Middleware\RateLimitApi;
use App\Http\Middleware\RequireLaunchFeature;
use App\Http\Middleware\SetSessionCookieByGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        using: function () {
            Route::middleware('web')->group(base_path('routes/web.php'));
            Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));
            // Integration API disabled — not needed for Phase 1, security risk
            // Route::middleware('api')->prefix('api/v1')
            //     ->group(base_path('routes/integration.php'));
            Route::get('/up', function () {
                return response()->json(['status' => 'ok']);
            });
            Route::get('/health', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', SetSessionCookieByGuard::class);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'auth.integration' => AuthenticateIntegrationRequest::class,
            'integration.scope' => IntegrationScope::class,
            'integration.tenant' => IntegrationTenant::class,
            'auth.optional' => OptionalSanctumAuthentication::class,
            'vendor.owner' => EnsureVendorOwnership::class,
            'throttle.api' => RateLimitApi::class,
            'launch' => RequireLaunchFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
