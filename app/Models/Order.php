<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'order_number',
        'client_reference',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_address',
        'delivery_pincode',
        'customer_latitude',
        'customer_longitude',
        'delivery_method',
        'delivery_time_slot',
        'estimated_ready_at',
        'subtotal',
        'tax',
        'delivery_fee',
        'discount',
        'total',
        'status',
        'payment_status',
        'payment_method',
        'notes',
        'cancellation_reason',
        'confirmed_at',
        'preparing_at',
        'ready_at',
        'out_for_delivery_at',
        'delivered_at',
        'cancelled_at',
        'rejection_reason',
        'rejected_at',
        'refund_reason',
        'refunded_at',
        'inventory_released_at',
        'metadata',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'customer_latitude' => 'decimal:7',
        'customer_longitude' => 'decimal:7',
        'confirmed_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
        'refunded_at' => 'datetime',
        'inventory_released_at' => 'datetime',
        'estimated_ready_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'billable');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'delivered');
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('total_price');
        $this->total = $this->subtotal + $this->tax + $this->delivery_fee - $this->discount;
        $this->save();
    }

    public function markConfirmed(): void
    {
        $this->update(['status' => 'confirmed', 'confirmed_at' => now()]);
    }

    public function markPreparing(): void
    {
        $this->update(['status' => 'preparing']);
    }

    public function markReady(): void
    {
        $this->update(['status' => 'ready', 'ready_at' => now()]);
    }

    public function markOutForDelivery(): void
    {
        $this->update(['status' => 'out_for_delivery']);
    }

    public function markDelivered(): void
    {
        $this->update(['status' => 'delivered', 'delivered_at' => now()]);
    }

    public function markCancelled(?string $reason = null): void
    {
        $this->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
    }

    public function markRejected(?string $reason = null): void
    {
        $this->update(['status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => $reason]);
    }

    /**
     * Ordered delivery timeline. Only reached steps carry a timestamp. Terminal
     * (cancelled/rejected/refunded) steps short-circuit the remaining steps.
     */
    public function trackingTimeline(): array
    {
        $steps = [];

        if ($this->terminalStatus()) {
            $label = match ($this->status) {
                'cancelled' => 'cancelled',
                'rejected' => 'rejected',
                'refunded' => 'refunded',
                default => $this->status,
            };
            $at = $this->{($this->status === 'cancelled' ? 'cancelled_at' : 'rejected_at')};

            return [
                'timeline' => [[
                    'status' => $this->status,
                    'label' => ucfirst($label),
                    'at' => $at?->toIso8601String() ?: $this->updated_at?->toIso8601String(),
                ]],
                'current_status' => $this->status,
                'delivery_method' => $this->delivery_method,
            ];
        }

        $map = [
            'pending' => ['pending', 'Order placed', 'created_at'],
            'confirmed' => ['confirmed', 'Order confirmed', 'confirmed_at'],
            'preparing' => ['preparing', 'Being prepared', 'preparing_at'],
            'ready' => ['ready', $this->delivery_method === 'pickup' ? 'Ready for pickup' : 'Ready for delivery', 'ready_at'],
            'out_for_delivery' => ['out_for_delivery', 'Out for delivery', 'out_for_delivery_at'],
            'delivered' => ['delivered', 'Delivered', 'delivered_at'],
        ];

        $reached = $this->trackingIndex($this->status);

        $orderKeys = array_keys($map);
        foreach ($orderKeys as $index => $step) {
            $status = $map[$step][0];
            $at = $this->{$map[$step][2]};

            $steps[] = [
                'status' => $status,
                'label' => $map[$step][1],
                'at' => $at?->toIso8601String(),
                'reached' => $index <= $reached,
            ];
        }

        return [
            'timeline' => $steps,
            'current_status' => $this->status,
            'delivery_method' => $this->delivery_method,
        ];
    }

    private function terminalStatus(): bool
    {
        return in_array($this->status, ['cancelled', 'rejected', 'refunded'], true);
    }

    private function trackingIndex(string $status): int
    {
        return match ($status) {
            'pending' => 0,
            'confirmed' => 1,
            'preparing' => 2,
            'ready' => 3,
            'out_for_delivery' => 4,
            'delivered' => 5,
            default => -1,
        };
    }
}
