<?php

namespace App\Models;

use App\Services\BusinessModuleService;
use App\Services\Experience\BusinessExperienceService;
use App\Services\LaunchControlService;
use App\Services\MonetizationService;
use App\Services\PlanGate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'timezone',
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
        'city_id',
        'state',
        'last_synced_at',
        'created_by',
        'enabled_modules',
        'module_config',
        'payment_methods',
        'tax_percent',
        'discount_amount',
        'commission_percent',
        'primary_experience',
        'enabled_experiences',
        'experience_config',
        'availability_updated_at',
        'availability_is_stale',
        'claim_notifications_enabled',
        'claim_notification_delay_days',
        'claim_preferred_channel',
        'claim_auto_approve',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'photos' => 'array',
        'working_hours' => 'array',
        'timezone' => 'string',
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
        'enabled_experiences' => 'array',
        'experience_config' => 'array',
        'availability_updated_at' => 'datetime',
        'availability_is_stale' => 'boolean',
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
        $approved = $this->reviews()->where('status', 'approved');
        $this->average_rating = round((float) $approved->avg('rating'), 1);
        $this->review_count = $approved->count();
        $this->saveQuietly();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function subcategory(): BelongsTo
    {
        // Legacy subcategories were migrated into level-2 Category children
        // (migrate_subcategories_to_category_children); subcategory_id now
        // references those children directly.
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function legacySubcategory(): BelongsTo
    {
        // Pre-migration fallback for businesses still pointing at the
        // read-only legacy subcategories table.
        return $this->belongsTo(Subcategory::class, 'subcategory_id');
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

    public function subscription(): HasOne
    {
        return $this->hasOne(BusinessSubscription::class)->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(BusinessSubscription::class);
    }

    /**
     * Can this business use the given plan feature? (e.g. 'bookings', 'shopping', 'featured')
     */
    public function can(string $feature): bool
    {
        return app(PlanGate::class)->can($this, $feature);
    }

    /**
     * The active subscription plan (or the free default).
     */
    public function plan(): SubscriptionPlan
    {
        return app(PlanGate::class)->planFor($this);
    }

    /**
     * The first displayable photo as a string (handles photo_reference arrays
     * that the AI importer may store before they are downloaded server-side).
     */
    public function primaryPhoto(): ?string
    {
        $photos = $this->resolvedPhotoUrls();
        if (empty($photos)) {
            return null;
        }

        // Prefer photos already stored locally/CDN (they load without extra keys).
        foreach ($photos as $photo) {
            if (! str_contains($photo, 'maps.googleapis.com/')) {
                return $photo;
            }
        }

        return $photos[0];
    }

    /**
     * All displayable photos as plain string URLs. photo_reference arrays are
     * expanded into real Google Places photo URLs at serialization time so the
     * app/API never receives raw reference maps (which break strict parsing).
     */
    public function photoUrls(): array
    {
        return $this->resolvedPhotoUrls();
    }

    /**
     * Resolve the raw `photos` column into displayable URLs.
     *
     * - Local/CDN paths (storage/..., http...) are kept as-is.
     * - Google Places photo URLs that lack a `key` are rebuilt with the
     *   configured key, so the app never receives a guaranteed-400 URL.
     * - Google photo_reference arrays are expanded server-side with the key.
     * - If no key is configured, key-less Google URLs are dropped so clients
     *   don't attempt to load broken images.
     */
    private function resolvedPhotoUrls(): array
    {
        if (empty($this->photos) || ! is_array($this->photos)) {
            return [];
        }

        $apiKey = \App\Models\Setting::get('api_key_google_places')
            ?? config('services.google.places_api_key');

        $urls = [];
        foreach ($this->photos as $photo) {
            if (is_string($photo) && $photo !== '') {
                if (str_contains($photo, 'maps.googleapis.com/maps/api/place/photo')) {
                    if (str_contains($photo, 'key=')) {
                        $urls[] = $photo;
                    } elseif ($apiKey) {
                        $urls[] = $photo
                            .(str_contains($photo, '?') ? '&' : '?')
                            .'key='.rawurlencode($apiKey);
                    }
                    // else: no key configured — drop the guaranteed-400 URL.
                } else {
                    $urls[] = $photo;
                }
            } elseif (is_array($photo) && ! empty($photo['photo_reference']) && $apiKey) {
                $urls[] = 'https://maps.googleapis.com/maps/api/place/photo'
                    .'?photoreference='.rawurlencode($photo['photo_reference'])
                    .'&maxwidth=800&key='.rawurlencode($apiKey);
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Plan + feature summary for the app/vendor UI.
     */
    public function planInfo(): array
    {
        $plan = $this->plan();
        $subscription = $this->subscription;
        $planGate = app(PlanGate::class);

        return [
            'plan' => $plan->name,
            'plan_slug' => $plan->slug,
            'plan_id' => $plan->id,
            'subscription_status' => $subscription?->status ?? 'free',
            'renews_at' => $subscription?->ends_at?->toDateString(),
            'commission_percent' => app(MonetizationService::class)->commissionPercentFor($this),
            'max_active_services' => $planGate->maxActiveServices($this),
            'features' => collect(array_keys(PlanGate::FEATURES))
                ->mapWithKeys(fn ($key) => [$key => $this->can($key)])
                ->all(),
        ];
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(MediaLibrary::class);
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

    public function schedules(): HasMany
    {
        return $this->hasMany(VehicleSchedule::class)->orderBy('departure_date')->orderBy('departure_time');
    }

    public function scheduleBookings(): HasMany
    {
        return $this->hasMany(ScheduleBooking::class);
    }

    public function vehicleRentals(): HasMany
    {
        return $this->hasMany(VehicleRental::class);
    }

    // Multi-classification relationships
    public function classifications(): HasMany
    {
        return $this->hasMany(BusinessClassification::class);
    }

    public function primaryClassification()
    {
        return $this->hasOne(BusinessClassification::class)->where('is_primary', true);
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function worlds()
    {
        return World::query()->whereHas('allCategories.classifications', function ($query) {
            $query->where('business_id', $this->getKey())->where('is_active', true);
        });
    }

    public function hasWorld(string $worldSlug): bool
    {
        return $this->worlds()->where('worlds.slug', $worldSlug)->exists();
    }

    public function hasClassification(int $categoryId): bool
    {
        return $this->classifications()->where('category_id', $categoryId)->exists();
    }

    /**
     * Single write-path for the primary classification (Phase 2.5: the
     * classification table is the source of truth for business↔category).
     * Every business-creating route (admin form, API, vendor) must go through
     * here so category counts never drift from classification counts.
     */
    public function syncPrimaryClassification(int $categoryId, string $source = 'system'): void
    {
        $primary = $this->primaryClassification()->first();

        if ($primary) {
            if ((int) $primary->category_id !== $categoryId) {
                $primary->update(['category_id' => $categoryId, 'source' => $source]);
            }

            return;
        }

        $this->classifications()->updateOrCreate(
            ['business_id' => $this->id, 'category_id' => $categoryId],
            ['is_primary' => true, 'is_active' => true, 'source' => $source],
        );
    }

    public function classificationsInWorld(int $worldId)
    {
        return $this->classifications()
            ->whereHas('category', fn ($q) => $q->where('world_id', $worldId));
    }

    public function hasBookingsModule(): bool
    {
        return $this->hasModule('bookings');
    }

    /**
     * A low-risk directory listing: no transactional modules enabled and no
     * transactional experiences. Such listings may auto-verify on claim;
     * transactional businesses must pass admin verification first.
     */
    public function isDirectoryOnly(): bool
    {
        $modules = is_array($this->enabled_modules) ? $this->enabled_modules : [];
        $hasTransactionalModule = collect($modules)
            ->filter(fn ($enabled) => filter_var($enabled, FILTER_VALIDATE_BOOL))
            ->isNotEmpty();

        if ($hasTransactionalModule) {
            return false;
        }

        $experiences = array_values(is_array($this->enabled_experiences) ? $this->enabled_experiences : ['directory']);

        return collect($experiences)->filter(fn (string $experience) => $experience !== 'directory')->isEmpty();
    }

    public function hasOrdersModule(): bool
    {
        return $this->hasModule('orders');
    }

    public function hasTransportModule(): bool
    {
        return $this->hasModule('transport');
    }

    public function hasTurfModule(): bool
    {
        return $this->hasModule('turf');
    }

    public function hasModule(string $module): bool
    {
        return (bool) (app(BusinessModuleService::class)->effectiveFor($this)[$module] ?? false);
    }

    /**
     * Unified "Discover & Book" capability — used by the app's single discover
     * page. A business can be booked directly in-app only when it is claimed,
     * verified, has the bookings module enabled, is globally switched on, and
     * has at least one ready booking experience. Otherwise the app shows a
     * call/WhatsApp CTA via `book_cta`.
     */
    public function bookingCapability(): array
    {
        $launchControl = app(LaunchControlService::class);
        $experienceService = app(BusinessExperienceService::class);

        $claimed = (bool) $this->created_by;
        $verified = ($this->verification_status ?? 'pending') === 'verified';
        $moduleOn = $launchControl->moduleEnabled('bookings') && $this->hasModule('bookings');

        $experienceBase = $this->enabled_experiences;
        if ($experienceBase === null || $experienceBase === []) {
            $experienceBase = $moduleOn ? ['appointment', 'stay', 'turf', 'seat_event'] : ['directory'];
        }

        $readiness = $experienceService->calculateReadinessForExperiences($this, $experienceBase);
        $experiences = $launchControl->filterExperiences($experienceBase);
        $readyExperiences = collect($experiences)->filter(
            fn (string $experience) => $experience !== 'directory'
                && ($readiness[$experience]['ready'] ?? false)
                && $launchControl->experienceEnabled($experience),
        )->values()->all();

        $canBookOnline = $claimed && $verified && $moduleOn && count($readyExperiences) > 0;

        $cta = $canBookOnline ? 'in_app' : (($this->whatsapp ?? $this->phone) ? 'call_or_whatsapp' : 'none');

        return [
            'can_book_online' => $canBookOnline,
            'book_cta' => $cta,
            'ready_experiences' => $readyExperiences,
            'primary_experience' => $readyExperiences[0] ?? null,
            'contact' => [
                'phone' => $this->phone,
                'whatsapp' => $this->whatsapp,
            ],
        ];
    }

    public function effectiveModules(): array
    {
        return app(BusinessModuleService::class)->effectiveFor($this);
    }

    public function moduleReadiness(): array
    {
        return app(BusinessModuleService::class)->readiness($this);
    }

    protected static function booted()
    {
        static::creating(function ($business) {
            if ($business->category_id && $business->enabled_modules === null) {
                $business->setRelation('category', Category::find($business->category_id));
                $business->enabled_modules = app(BusinessModuleService::class)->effectiveFor($business);
            }

            if ($business->enabled_modules !== null) {
                $modules = app(BusinessModuleService::class)->normalize($business->enabled_modules);
                $business->enabled_modules = $modules;
                $business->service_type = app(BusinessModuleService::class)->legacyServiceType($modules);
                $business->is_bookable = $modules['bookings'] || $modules['transport'];
            }
        });

        static::updating(function ($business) {
            if ($business->isDirty('enabled_modules')) {
                $modules = app(BusinessModuleService::class)->normalize($business->enabled_modules);
                $business->enabled_modules = $modules;
                $business->service_type = app(BusinessModuleService::class)->legacyServiceType($modules);
                $business->is_bookable = $modules['bookings'] || $modules['transport'];
            }
        });
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
        return $query->where(function ($serviceabilityQuery) {
            $serviceabilityQuery->whereHas('pincodeData', function ($pincodeQuery) {
                $pincodeQuery->where('serviceable', true);
            })->orWhere(function ($districtFallbackQuery) {
                $districtFallbackQuery->whereNull('pincode')
                    ->whereNotNull('district')
                    ->whereExists(function ($pincodeQuery) {
                        $pincodeQuery->selectRaw('1')
                            ->from('pincodes')
                            ->whereColumn('pincodes.district', 'businesses.district')
                            ->where('pincodes.serviceable', true);
                    });
            });
        });
    }

    public function scopeOfModule($query, string $module)
    {
        $modules = collect(explode(',', $module))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->map(fn ($value) => match ($value) {
                'shopping', 'ordering' => 'catalog',
                'order', 'orders' => 'orders',
                'booking', 'bookings' => 'bookings',
                default => $value,
            })
            ->filter(fn ($value) => $value === 'directory' || in_array($value, app(BusinessModuleService::class)->keys(), true))
            ->unique()
            ->values();

        if ($modules->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $legacyTypes = [
            'catalog' => ['ordering', 'both'],
            'orders' => ['ordering', 'both'],
            'bookings' => ['booking', 'both', 'turf'],
            'inventory' => ['ordering', 'both'],
            'transport' => ['transport'],
            'turf' => ['turf'],
        ];

        return $query->where(function ($q) use ($legacyTypes, $modules) {
            foreach ($modules as $index => $moduleName) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $q->{$method}(function ($moduleQuery) use ($legacyTypes, $moduleName) {
                    if ($moduleName === 'directory') {
                        $moduleQuery->where(function ($configuredQuery) {
                            $configuredQuery->whereNotNull('enabled_modules');
                            foreach (['catalog', 'bookings', 'transport', 'turf'] as $capability) {
                                $configuredQuery->where(function ($capabilityQuery) use ($capability) {
                                    $capabilityQuery->where("enabled_modules->{$capability}", false)
                                        ->orWhereNull("enabled_modules->{$capability}");
                                });
                            }
                        })->orWhere(function ($legacyQuery) {
                            $legacyQuery->whereNull('enabled_modules')
                                ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('module_type', 'directory'));
                        });

                        return;
                    }

                    $moduleQuery->where("enabled_modules->{$moduleName}", true)
                        ->orWhere(function ($legacyQuery) use ($legacyTypes, $moduleName) {
                            $legacyQuery->whereNull('enabled_modules')
                                ->whereHas('category', fn ($categoryQuery) => $categoryQuery->whereIn('module_type', $legacyTypes[$moduleName]));
                        });
                });
            }
        });
    }
}
