<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthenticateIntegrationRequest;
use App\Http\Middleware\IntegrationScope;
use App\Http\Middleware\IntegrationTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        using: function () {
            Route::middleware('web')->group(base_path('routes/web.php'));
            Route::middleware('api')->group(base_path('routes/api.php'));
            Route::middleware('api')->prefix('api/v1')->name('integration.')
                ->group(base_path('routes/integration.php'));
            Route::get('/up', function () {
                return response()->json(['status' => 'ok']);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', \App\Http\Middleware\SetSessionCookieByGuard::class);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'auth.integration' => AuthenticateIntegrationRequest::class,
            'integration.scope' => IntegrationScope::class,
            'integration.tenant' => IntegrationTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
