<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSetup extends Model
{
    protected $fillable = [
        'business_id',
        'profile_complete',
        'products_added',
        'photos_uploaded',
        'operating_hours_set',
        'delivery_configured',
        'first_order_received',
        'first_booking_received',
        'completed_at',
    ];

    protected $casts = [
        'profile_complete' => 'boolean',
        'products_added' => 'boolean',
        'photos_uploaded' => 'boolean',
        'operating_hours_set' => 'boolean',
        'delivery_configured' => 'boolean',
        'first_order_received' => 'boolean',
        'first_booking_received' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Recompute the readiness flags from the business's real state so the web
     * dashboard and the API surface agree on one source of truth.
     */
    public static function syncFromBusiness(Business $business): self
    {
        $setup = static::firstOrCreate(
            ['business_id' => $business->id],
            ['business_id' => $business->id]
        );

        $setup->update([
            'profile_complete' => (bool) ($business->name && $business->phone && $business->address && $business->category_id),
            'products_added' => $business->products()->where('is_active', true)->exists()
                || $business->services()->where('is_active', true)->exists(),
            'photos_uploaded' => $business->media()->exists(),
            'operating_hours_set' => is_array($business->working_hours) && count($business->working_hours) > 0,
            'delivery_configured' => $business->deliveryZones()->where('is_active', true)->exists(),
            'first_order_received' => $business->orders()->exists(),
            'first_booking_received' => $business->bookings()->exists(),
        ]);

        $setup->markCompleteIfReady();

        return $setup;
    }

    public function nextStep(): ?string
    {
        return collect($this->checklist)
            ->first(fn (array $item) => ! $item['done'])['label'] ?? null;
    }

    public function calculateCompletionPercentage(): int
    {
        $steps = [
            'profile_complete',
            'products_added',
            'photos_uploaded',
            'operating_hours_set',
        ];

        $completed = collect($steps)->filter(fn ($field) => $this->{$field})->count();

        return (int) round(($completed / count($steps)) * 100);
    }

    public function markCompleteIfReady(): void
    {
        $allDone = $this->profile_complete
            && $this->products_added
            && $this->photos_uploaded
            && $this->operating_hours_set;

        if ($allDone && ! $this->completed_at) {
            $this->update(['completed_at' => now()]);
        }
    }

    public function getChecklistAttribute(): array
    {
        return [
            ['key' => 'profile_complete', 'label' => 'Complete business profile', 'done' => $this->profile_complete],
            ['key' => 'products_added', 'label' => 'Add products or services', 'done' => $this->products_added],
            ['key' => 'photos_uploaded', 'label' => 'Upload photos', 'done' => $this->photos_uploaded],
            ['key' => 'operating_hours_set', 'label' => 'Set operating hours', 'done' => $this->operating_hours_set],
            ['key' => 'delivery_configured', 'label' => 'Configure delivery', 'done' => $this->delivery_configured],
            ['key' => 'first_order_received', 'label' => 'Receive first order', 'done' => $this->first_order_received],
            ['key' => 'first_booking_received', 'label' => 'Receive first booking', 'done' => $this->first_booking_received],
        ];
    }
}
