<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminDepartmentScope
{
    /**
     * Route prefixes every staff member may access regardless of department.
     */
    private const SHARED = [
        'admin.dashboard',
        'admin.users',
        'admin.vendors',
    ];

    /**
     * Route prefixes shared by operational departments (not support).
     */
    private const ANALYTICS = [
        'admin.analytics',
        'admin.search-history',
        'admin.reports',
    ];

    /**
     * Route prefixes owned by the Directory department.
     */
    private const DIRECTORY = [
        'admin.businesses',
        'admin.claims',
        'admin.import',
        'admin.category-tree',
        'admin.categories',
        'admin.subcategories',
        'admin.taxonomy',
        'admin.business-types',
        'admin.businesses-type',
        'admin.classification-audit',
        'admin.featured',
        'admin.homepage',
        'admin.area-interests',
        'admin.reviews',
    ];

    /**
     * Route prefixes owned by the Shopping department.
     */
    private const SHOPPING = [
        'admin.products',
        'admin.orders',
        'admin.product-categories',
        'admin.shop-sections',
        'admin.areas',
    ];

    /**
     * Route prefixes owned by the Booking department.
     */
    private const BOOKING = [
        'admin.services',
        'admin.bookings',
    ];

    /**
     * Route prefixes owned by the Taxi / Transport department.
     */
    private const TAXI = [
        'admin.vehicle-types',
        'admin.pincodes',
    ];

    /**
     * Route prefixes visible to support staff (customer service only).
     */
    private const SUPPORT = [
        'admin.dashboard',
        'admin.users',
        'admin.vendors',
        'admin.reviews',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $department = $user->adminDepartment();

        // Full-access staff (no department) can reach everything; role checks handle power pages.
        if ($department === null) {
            return $next($request);
        }

        $sets = [
            'directory' => [...self::DIRECTORY, ...self::ANALYTICS],
            'shopping' => [...self::SHOPPING, ...self::ANALYTICS],
            'booking' => [...self::BOOKING, ...self::ANALYTICS],
            'taxi' => [...self::TAXI, ...self::ANALYTICS],
            'support' => self::SUPPORT,
        ];

        $allowed = array_merge(self::SHARED, $sets[$department] ?? []);

        $routeName = (string) $request->route()?->getName();

        if ($routeName !== '' && $request->routeIs($allowed)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Unauthorized. Access outside your department.'], 403);
        }

        abort(403, 'Access outside your department.');
    }
}
