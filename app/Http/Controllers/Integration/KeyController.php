<?php

namespace App\Http\Controllers\Integration;

use App\Models\IntegrationApiKey;
use App\Models\Scopes;
use Illuminate\Http\Request;

class KeyController extends BaseController
{
    public function index(Request $request)
    {
        $keys = IntegrationApiKey::select('id', 'name', 'key_prefix', 'scopes', 'tenant_type', 'tenant_id', 'created_by', 'is_revoked', 'expires_at', 'last_used_at', 'created_at')
            ->when($request->tenant_type, fn ($q, $v) => $q->where('tenant_type', $v))
            ->when($request->tenant_id, fn ($q, $v) => $q->where('tenant_id', $v))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return $this->ok($keys);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'required|array',
            'scopes.*' => 'string|in:' . implode(',', Scopes::all()),
            'tenant_type' => 'nullable|string|max:50',
            'tenant_id' => 'nullable|integer|exists:users,id',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $result = IntegrationApiKey::generateKey(
            $validated['name'],
            $validated['scopes'],
            ['type' => $validated['tenant_type'] ?? null, 'id' => $validated['tenant_id'] ?? null],
            $request->user()?->id,
            $validated['allowed_ips'] ?? null,
            $validated['expires_at'] ?? null,
        );

        return $this->created([
            'id' => $result['key']->id,
            'name' => $result['key']->name,
            'key_prefix' => $result['key']->key_prefix,
            'scopes' => $result['key']->scopes,
            'api_key' => $result['raw_key'],
            'warning' => 'Store this key securely. It will not be shown again.',
        ]);
    }

    public function show(Request $request, $id)
    {
        $key = IntegrationApiKey::findOrFail($id);
        return $this->ok($key->makeHidden('key_hash'));
    }

    public function update(Request $request, $id)
    {
        $key = IntegrationApiKey::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'scopes' => 'sometimes|array',
            'scopes.*' => 'string|in:' . implode(',', Scopes::all()),
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'expires_at' => 'nullable|date',
        ]);

        $key->update($validated);

        return $this->ok($key->fresh()->makeHidden('key_hash'), 'API key updated.');
    }

    public function revoke($id)
    {
        $key = IntegrationApiKey::findOrFail($id);
        $key->update(['is_revoked' => true]);
        return $this->ok(null, 'API key revoked.');
    }

    public function rotate(Request $request, $id)
    {
        $key = IntegrationApiKey::findOrFail($id);

        $result = IntegrationApiKey::generateKey(
            $key->name,
            $key->scopes,
            ['type' => $key->tenant_type, 'id' => $key->tenant_id],
            $key->created_by,
            $key->allowed_ips,
            $key->expires_at?->toDateTimeString(),
        );

        $key->update(['is_revoked' => true]);

        return $this->ok([
            'id' => $result['key']->id,
            'name' => $result['key']->name,
            'key_prefix' => $result['key']->key_prefix,
            'api_key' => $result['raw_key'],
            'warning' => 'Old key has been revoked. Store this new key securely.',
        ], 'API key rotated.');
    }
}
