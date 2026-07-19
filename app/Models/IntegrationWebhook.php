<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationWebhook extends Model
{
    protected $table = 'integration_webhooks';

    protected $fillable = [
        'tenant_type',
        'tenant_id',
        'url',
        'secret',
        'events',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public function deliveries()
    {
        return $this->hasMany(IntegrationWebhookDelivery::class, 'webhook_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }

    public function scopeForTenant($query, string $tenantType, int $tenantId)
    {
        return $query->where('tenant_type', $tenantType)->where('tenant_id', $tenantId);
    }

    public static function booted(): void
    {
        static::creating(function ($webhook) {
            if (empty($webhook->secret)) {
                $webhook->secret = 'whsec_' . \Illuminate\Support\Str::random(32);
            }
        });
    }
}
