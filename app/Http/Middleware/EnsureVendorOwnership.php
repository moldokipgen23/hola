<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $businessId = $this->resolveBusinessId($request);

        if (! $businessId) {
            return $next($request);
        }

        $owns = Business::where('id', $businessId)
            ->where('created_by', $user->id)
            ->exists();

        if (! $owns) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not own this business resource.',
                ], 403);
            }

            abort(403, 'You do not own this business resource.');
        }

        return $next($request);
    }

    protected function resolveBusinessId(Request $request): ?int
    {
        $route = $request->route();

        if ($route) {
            foreach (['businessId', 'business_id', 'id'] as $param) {
                if ($route->hasParameter($param)) {
                    $paramName = $param;

                    if ($param === 'id') {
                        $paramName = $this->guessModelBusinessId($request, $route);
                    }

                    if ($paramName === 'id' || $paramName === 'businessId' || $paramName === 'business_id') {
                        $value = $route->parameter($paramName) ?? $route->parameter($param);
                        if ($value) {
                            return $this->extractBusinessIdFromParameter($request, (string) $value, $param);
                        }
                    }
                }
            }
        }

        if ($request->filled('business_id')) {
            return (int) $request->input('business_id');
        }

        $routeName = $route?->getName() ?? '';

        if (str_contains($routeName, 'products')) {
            return $this->getBusinessIdFromProduct($request);
        }

        if (str_contains($routeName, 'orders')) {
            return $this->getBusinessIdFromOrder($request);
        }

        if (str_contains($routeName, 'bookings')) {
            return $this->getBusinessIdFromBooking($request);
        }

        if (str_contains($routeName, 'services')) {
            return $this->getBusinessIdFromService($request);
        }

        return null;
    }

    protected function guessModelBusinessId(Request $request, $route): string
    {
        $segments = explode('.', $route->getName() ?? '');

        foreach ($segments as $segment) {
            if (str_ends_with($segment, 'products') || str_ends_with($segment, 'product')) {
                return 'businessId';
            }
            if (str_ends_with($segment, 'orders') || str_ends_with($segment, 'order')) {
                return 'businessId';
            }
            if (str_ends_with($segment, 'bookings') || str_ends_with($segment, 'booking')) {
                return 'businessId';
            }
            if (str_ends_with($segment, 'services') || str_ends_with($segment, 'service')) {
                return 'businessId';
            }
            if (str_ends_with($segment, 'vehicles') || str_ends_with($segment, 'vehicle')) {
                return 'businessId';
            }
        }

        return 'id';
    }

    protected function extractBusinessIdFromParameter(Request $request, string $value, string $param): ?int
    {
        if ($param === 'businessId' || $param === 'business_id') {
            return (int) $value;
        }

        return null;
    }

    protected function getBusinessIdFromProduct(Request $request): ?int
    {
        $productId = $request->route('id') ?? $request->route('productId');

        if ($productId) {
            $product = Product::find($productId);

            return $product?->business_id;
        }

        return null;
    }

    protected function getBusinessIdFromOrder(Request $request): ?int
    {
        $orderId = $request->route('id');

        if ($orderId) {
            $order = Order::find($orderId);

            return $order?->business_id;
        }

        return null;
    }

    protected function getBusinessIdFromBooking(Request $request): ?int
    {
        $bookingId = $request->route('id');

        if ($bookingId) {
            $booking = Booking::find($bookingId);

            return $booking?->business_id;
        }

        return null;
    }

    protected function getBusinessIdFromService(Request $request): ?int
    {
        $serviceId = $request->route('id') ?? $request->route('serviceId');

        if ($serviceId) {
            $service = Service::find($serviceId);

            return $service?->business_id;
        }

        return null;
    }
}
