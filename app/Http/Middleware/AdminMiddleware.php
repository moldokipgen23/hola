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
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!in_array($user->role, self::ROLES)) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if ($level === 'super_admin' && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
        }

        return $next($request);
    }
}
