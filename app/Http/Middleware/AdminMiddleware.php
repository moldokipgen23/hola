<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    private const ROLES = ['super_admin', 'admin', 'moderator'];

    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('admin.login');
        }

        if (!in_array($user->role, self::ROLES)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }
            return redirect()->route('admin.login')->with('error', 'Admin access required.');
        }

        if ($level === 'super_admin' && $user->role !== 'super_admin') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
            }
            return redirect()->route('admin.login')->with('error', 'Super admin access required.');
        }

        return $next($request);
    }
}
