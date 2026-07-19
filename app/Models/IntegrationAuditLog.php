<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationAuditLog extends Model
{
    protected $table = 'integration_audit_logs';

    protected $fillable = [
        'tenant_type',
        'tenant_id',
        'actor_type',
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'request_method',
        'request_path',
        'request_ip',
        'user_agent',
        'payload',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'changes' => 'array',
        ];
    }

    public function scopeForTenant($query, string $tenantType, int $tenantId)
    {
        return $query->where('tenant_type', $tenantType)->where('tenant_id', $tenantId);
    }

    public static function log(array $data): self
    {
        return static::create($data);
    }
}
