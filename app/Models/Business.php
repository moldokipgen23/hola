<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'subcategory_id',
        'area_id',
        'name',
        'slug',
        'description',
        'address',
        'locality',
        'district',
        'latitude',
        'longitude',
        'phone',
        'whatsapp',
        'email',
        'website',
        'photos',
        'photos_downloaded_at',
        'working_hours',
        'claim_status',
        'verification_status',
        'source',
        'external_id',
        'import_batch_id',
        'confidence',
        'is_featured',
        'is_active',
        'views_count',
        'saves_count',
        'average_rating',
        'review_count',
        'call_count',
        'whatsapp_count',
        'directions_count',
        'share_count',
        'service_type',
        'is_bookable',
        'price_range',
        'delivery_radius_km',
        'pincode',
        'state',
        'last_synced_at',
        'created_by',
        'enabled_modules',
        'module_config',
        'payment_methods',
        'claim_notifications_enabled',
        'claim_notification_delay_days',
        'claim_preferred_channel',
        'claim_auto_approve',
    ];

    protected $casts = [
        'photos' => 'array',
        'working_hours' => 'array',
        'photos_downloaded_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'views_count' => 'integer',
        'saves_count' => 'integer',
        'call_count' => 'integer',
        'whatsapp_count' => 'integer',
        'directions_count' => 'integer',
        'share_count' => 'integer',
        'is_bookable' => 'boolean',
        'price_range' => 'integer',
        'delivery_radius_km' => 'decimal:2',
        'average_rating' => 'float',
        'review_count' => 'integer',
        'enabled_modules' => 'array',
        'module_config' => 'array',
        'payment_methods' => 'array',
    ];

    protected $appends = ['quality_score'];

    public function getQualityScoreAttribute(): int
    {
        $score = 0;
        if ($this->name) {
            $score += 15;
        }
        if ($this->description && strlen($this->description) > 20) {
            $score += 15;
        }
        if ($this->address) {
            $score += 10;
        }
        if ($this->phone) {
            $score += 15;
        }
        if ($this->latitude && $this->longitude) {
            $score += 10;
        }
        if ($this->working_hours) {
            $score += 10;
        }
        if ($this->photos && count($this->photos) > 0) {
            $score += 15;
        }
        if ($this->email) {
            $score += 5;
        }
        if ($this->whatsapp) {
            $score += 5;
        }

        return min($score, 100);
    }

    public function updateRatingStats(): void
    {
        $this->average_rating = round($this->reviews()->avg('rating'), 1);
        $this->review_count = $this->reviews()->count();
        $this->saveQuietly();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort_order');
    }

    public function savedByUsers(): HasMany
    {
        return $this->hasMany(SavedListing::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function claimRequests(): HasMany
    {
        return $this->hasMany(ClaimRequest::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Module relationships
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->orderByDesc('booking_date');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderByDesc('created_at');
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function pincodeData(): BelongsTo
    {
        return $this->belongsTo(Pincode::class, 'pincode', 'pincode');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->orderBy('sort_order');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class)->orderByDesc('created_at');
    }

    public function hasBookingsModule(): bool
    {
        $modules = $this->enabled_modules;

        return $modules && ($modules['bookings'] ?? false);
    }

    public function hasOrdersModule(): bool
    {
        $modules = $this->enabled_modules;

        return $modules && ($modules['orders'] ?? false);
    }

    public function hasTransportModule(): bool
    {
        $modules = $this->enabled_modules;

        return $modules && ($modules['transport'] ?? false);
    }

    public function hasTurfModule(): bool
    {
        $modules = $this->enabled_modules;

        return $modules && ($modules['turf'] ?? false);
    }

    protected static function booted()
    {
        static::creating(function ($business) {
            if ($business->category_id && empty($business->enabled_modules)) {
                $category = Category::find($business->category_id);
                if ($category && $category->module_type !== 'directory') {
                    static::applyCategoryModules($business, $category);
                }
            }
        });

        static::updating(function ($business) {
            if ($business->isDirty('category_id') && ! $business->isDirty('enabled_modules')) {
                $category = Category::find($business->category_id);
                if ($category) {
                    static::applyCategoryModules($business, $category);
                }
            }
        });
    }

    protected static function applyCategoryModules($business, $category): void
    {
        $business->enabled_modules = match ($category->module_type) {
            'ordering' => ['catalog' => true, 'orders' => true, 'bookings' => false, 'inventory' => true],
            'booking' => ['catalog' => true, 'bookings' => true, 'orders' => false, 'inventory' => false],
            'both' => ['catalog' => true, 'bookings' => true, 'orders' => true, 'inventory' => true],
            'transport' => ['catalog' => true, 'transport' => true, 'bookings' => false, 'orders' => false, 'inventory' => false],
            'turf' => ['catalog' => true, 'turf' => true, 'bookings' => false, 'orders' => false, 'inventory' => false],
            default => ['catalog' => true, 'bookings' => false, 'orders' => false, 'inventory' => false],
        };
        $business->service_type = match ($category->module_type) {
            'ordering' => 'buyable',
            'booking' => 'bookable',
            'both' => 'hybrid',
            'transport' => 'transport',
            'turf' => 'turf',
            default => 'directory',
        };
        $business->is_bookable = in_array($category->module_type, ['booking', 'both', 'turf', 'transport']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch($query, $term)
    {
        $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function ($q) use ($safe) {
            $q->where('name', 'like', $safe)
                ->orWhere('description', 'like', $safe)
                ->orWhere('address', 'like', $safe);
        });
    }

    public function scopeInServiceableArea($query)
    {
        return $query->whereNotNull('pincode')->whereHas('pincodeData', function ($q) {
            $q->where('serviceable', true);
        });
    }

    public function scopeOfModule($query, string $module)
    {
        $modules = array_map('trim', explode(',', $module));

        return $query->whereHas('category', function ($q) use ($modules) {
            $q->whereIn('module_type', $modules);
            foreach (['ordering', 'booking'] as $single) {
                if (in_array($single, $modules)) {
                    $q->orWhere('module_type', 'both');
                }
            }
        });
    }
}
