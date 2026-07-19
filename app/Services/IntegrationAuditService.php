<?php

namespace App\Services;

use App\Models\IntegrationApiKey;
use App\Models\IntegrationAuditLog;
use App\Models\IntegrationOAuthAccessToken;
use Illuminate\Http\Request;

class IntegrationAuditService
{
    public static function log(Request $request, string $action, ?string $resourceType = null, ?string $resourceId = null, ?array $changes = null): IntegrationAuditLog
    {
        $auth = $request->input('integration_auth');

        $actorType = null;
        $actorId = null;

        if ($auth instanceof IntegrationApiKey) {
            $actorType = 'api_key';
            $actorId = $auth->id;
        } elseif ($auth instanceof IntegrationOAuthAccessToken) {
            $actorType = 'oauth_token';
            $actorId = $auth->id;
        }

        return IntegrationAuditLog::log([
            'tenant_type' => $request->input('integration_tenant_type'),
            'tenant_id' => $request->input('integration_tenant_id'),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'request_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => $request->except(['integration_auth', 'integration_scopes', 'integration_tenant_type', 'integration_tenant_id']),
            'changes' => $changes,
        ]);
    }
}
