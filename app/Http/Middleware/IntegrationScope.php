<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IntegrationScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $authScopes = $request->input('integration_scopes', []);

        if (in_array('*', $authScopes)) {
            return $next($request);
        }

        foreach ($scopes as $scope) {
            if (!in_array($scope, $authScopes)) {
                return response()->json([
                    'error' => 'insufficient_scope',
                    'message' => "Missing required scope: {$scope}",
                    'required_scope' => $scope,
                ], 403);
            }
        }

        return $next($request);
    }
}
