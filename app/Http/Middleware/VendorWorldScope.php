<?php

namespace App\Http\Middleware;

use App\Services\LaunchControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VendorWorldScope
{
    /**
     * Vendor route prefixes -> the world flag that must be enabled.
     */
    private const MAP = [
        'vendor.products' => 'shop',
        'vendor.orders' => 'shop',
        'vendor.services' => 'book',
        'vendor.bookings' => 'book',
        'vendor.vehicles' => 'ride',
        'vendor.trips' => 'ride',
    ];

    public function __construct(private readonly LaunchControlService $launchControl) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();

        foreach (self::MAP as $prefix => $world) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix.'.')) {
                if (! $this->launchControl->worldEnabled($world)) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => 'This feature is not currently available.',
                            'code' => 'feature_unavailable',
                            'feature' => $world,
                        ], 404);
                    }

                    return redirect()->route('vendor.dashboard')
                        ->with('error', 'This feature is not currently available.');
                }
            }
        }

        return $next($request);
    }
}
