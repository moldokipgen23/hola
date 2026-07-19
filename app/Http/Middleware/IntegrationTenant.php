<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiKey;
use App\Models\IntegrationOAuthAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IntegrationTenant
{
    public function handle(Request $request, Closure $next, string $tenantType): Response
    {
        $auth = $request->input('integration_auth');

        if (!$auth) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $authTenantType = $auth instanceof IntegrationApiKey
            ? $auth->tenant_type
            : ($auth->client->tenant_type ?? null);

        if ($authTenantType !== null && $authTenantType !== '*' && $authTenantType !== $tenantType) {
            return response()->json([
                'error' => 'tenant_mismatch',
                'message' => "This credential is not authorized for the '{$tenantType}' tenant.",
            ], 403);
        }

        if ($auth instanceof IntegrationApiKey && $auth->tenant_id) {
            $request->merge(['integration_tenant_id' => $auth->tenant_id]);
        }

        $request->merge(['integration_tenant_type' => $tenantType]);

        return $next($request);
    }
}
