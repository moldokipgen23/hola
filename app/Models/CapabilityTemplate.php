<?php

namespace App\Models;

use App\Services\BusinessExperienceService;
use App\Services\BusinessModuleService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapabilityTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'business_type',
        'description',
        'enabled_modules',
        'enabled_experiences',
        'default_availability',
        'fulfilment_options',
        'required_fields',
        'allowed_filters',
        'vendor_menu_config',
        'customer_cta_config',
        'metadata',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'enabled_modules' => 'array',
        'enabled_experiences' => 'array',
        'default_availability' => 'array',
        'fulfilment_options' => 'array',
        'required_fields' => 'array',
        'allowed_filters' => 'array',
        'vendor_menu_config' => 'array',
        'customer_cta_config' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function applyTo($business): void
    {
        $moduleService = app(BusinessModuleService::class);
        $moduleService->update($business, $this->enabled_modules);

        $experiences = array_values(array_unique($this->enabled_experiences ?? ['directory']));
        $business->forceFill([
            'enabled_experiences' => $experiences,
            'primary_experience' => collect($experiences)->first(fn ($experience) => $experience !== 'directory') ?? 'directory',
        ])->save();

        if ($this->default_availability['mode'] ?? null) {
            $experienceService = app(BusinessExperienceService::class);
            foreach ($experiences as $exp) {
                $experienceService->setAvailabilityMode($business, $exp, $this->default_availability['mode']);
            }
        }
    }
}
