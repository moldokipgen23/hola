<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class OptionalSanctumAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() && $request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($request->bearerToken());

            if ($accessToken && $accessToken->tokenable) {
                $request->setUserResolver(fn () => $accessToken->tokenable);
                $accessToken->forceFill(['last_used_at' => now()])->save();
            }
        }

        return $next($request);
    }
}
