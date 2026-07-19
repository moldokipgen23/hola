<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiKey;
use App\Models\IntegrationAuditLog;
use App\Models\IntegrationOAuthAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegrationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->resolveToken($request);

        if (!$token) {
            return response()->json(['error' => 'missing_credentials', 'message' => 'No API key or OAuth token provided.'], 401);
        }

        $auth = $this->authenticate($token);

        if (!$auth) {
            return response()->json(['error' => 'invalid_credentials', 'message' => 'API key or OAuth token is invalid.'], 401);
        }

        if ($auth instanceof IntegrationApiKey && !$auth->isValidForRequest($request->ip())) {
            return response()->json(['error' => 'forbidden', 'message' => 'API key is revoked, expired, or not allowed from this IP.'], 403);
        }

        if ($auth instanceof IntegrationOAuthAccessToken && $auth->is_revoked) {
            return response()->json(['error' => 'forbidden', 'message' => 'Token has been revoked.'], 403);
        }

        $request->merge(['integration_auth' => $auth]);
        $request->merge(['integration_scopes' => $auth instanceof IntegrationApiKey ? ($auth->scopes ?? []) : ($auth->scopes ?? [])]);

        return $next($request);
    }

    private function resolveToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        if ($key = $request->header('X-API-Key')) {
            return $key;
        }

        if ($key = $request->query('api_key')) {
            return $key;
        }

        return null;
    }

    private function authenticate(string $token): IntegrationApiKey|IntegrationOAuthAccessToken|null
    {
        if (str_starts_with($token, 'ehl_')) {
            $hashed = hash('sha256', $token);
            $key = IntegrationApiKey::where('key_hash', $hashed)->first();
            if ($key) {
                $key->touchLastUsed();
                return $key;
            }
            return null;
        }

        $accessToken = IntegrationOAuthAccessToken::where('id', $token)->valid()->first();
        if ($accessToken) {
            return $accessToken;
        }

        return null;
    }
}
