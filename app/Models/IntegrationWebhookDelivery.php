<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationWebhookDelivery extends Model
{
    protected $table = 'integration_webhook_deliveries';

    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response_code',
        'response_body',
        'status',
        'attempts',
        'next_attempt_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function webhook()
    {
        return $this->belongsTo(IntegrationWebhook::class, 'webhook_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
