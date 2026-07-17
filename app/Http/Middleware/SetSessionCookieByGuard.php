<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSessionCookieByGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            config(['session.cookie' => 'admin_session']);
        } elseif ($path === 'vendor' || str_starts_with($path, 'vendor/')) {
            config(['session.cookie' => 'vendor_session']);
        }

        return $next($request);
    }
}
