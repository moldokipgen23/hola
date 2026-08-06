<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    private const ROLES = ['super_admin', 'admin', 'moderator', 'manager'];

    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.login');
        }

        if (! in_array($user->role, self::ROLES)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            return redirect()->route('admin.login')->with('error', 'Admin access required.');
        }

        if ($user->banned_at) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Your account has been suspended.'], 403);
            }
            auth()->logout();

            return redirect()->route('admin.login')->with('error', 'Your account has been suspended.');
        }

        if (property_exists($user, 'is_active') && ! $user->is_active) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Your account is inactive.'], 403);
            }
            auth()->logout();

            return redirect()->route('admin.login')->with('error', 'Your account is inactive.');
        }

        if ($level === 'super_admin' && $user->role !== 'super_admin') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
            }

            return redirect()->route('admin.login')->with('error', 'Super admin access required.');
        }

        // Platform-level routes (staff, settings, monetization, system) require
        // super_admin or admin. Managers/moderators are department-scoped only.
        if ($level === 'platform' && ! in_array($user->role, ['super_admin', 'admin'], true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthorized. Platform access required.'], 403);
            }

            return redirect()->route('admin.login')->with('error', 'Platform access required.');
        }

        return $next($request);
    }
}
