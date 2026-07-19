<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IntegrationApiKey extends Model
{
    protected $table = 'integration_api_keys';

    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'scopes',
        'tenant_type',
        'tenant_id',
        'allowed_ips',
        'expires_at',
        'created_by',
        'is_revoked',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'allowed_ips' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'is_revoked' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_revoked', false)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes) || in_array($scope, $scopes);
    }

    public function hasAnyScope(array $scopes): bool
    {
        $keyScopes = $this->scopes ?? [];
        if (in_array('*', $keyScopes)) {
            return true;
        }
        return !empty(array_intersect($scopes, $keyScopes));
    }

    public static function generateKey(string $name, array $scopes = ['*'], ?array $tenant = null, ?int $createdBy = null, ?array $allowedIps = null, ?string $expiresAt = null): array
    {
        $raw = 'ehl_' . Str::random(48);
        $prefix = substr($raw, 0, 8);

        $key = static::create([
            'name' => $name,
            'key_hash' => hash('sha256', $raw),
            'key_prefix' => $prefix,
            'scopes' => $scopes,
            'tenant_type' => $tenant['type'] ?? null,
            'tenant_id' => $tenant['id'] ?? null,
            'allowed_ips' => $allowedIps,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        return ['key' => $key, 'raw_key' => $raw];
    }

    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function isValidForRequest(string $ip): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->allowed_ips && !in_array($ip, $this->allowed_ips)) {
            return false;
        }

        return true;
    }
}
