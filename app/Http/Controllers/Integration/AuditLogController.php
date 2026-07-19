<?php

namespace App\Http\Controllers\Integration;

use App\Models\IntegrationAuditLog;
use Illuminate\Http\Request;

class AuditLogController extends BaseController
{
    public function index(Request $request)
    {
        $logs = IntegrationAuditLog::when($request->tenant_type, fn ($q, $v) => $q->where('tenant_type', $v))
            ->when($request->tenant_id, fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($request->action, fn ($q, $v) => $q->where('action', $v))
            ->when($request->actor_type, fn ($q, $v) => $q->where('actor_type', $v))
            ->when($request->resource_type, fn ($q, $v) => $q->where('resource_type', $v))
            ->when($request->resource_id, fn ($q, $v) => $q->where('resource_id', $v))
            ->latest()
            ->paginate($request->per_page ?? 50);

        return $this->ok($logs);
    }

    public function show($id)
    {
        return $this->ok(IntegrationAuditLog::findOrFail($id));
    }
}
