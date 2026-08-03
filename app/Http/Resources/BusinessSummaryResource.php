<?php

namespace App\Http\Resources;

use App\Services\Experience\BusinessExperienceService;
use App\Services\LaunchControlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessSummaryResource extends JsonResource
{
    protected BusinessExperienceService $experienceService;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->experienceService = app(BusinessExperienceService::class);
    }

    public function toArray(Request $request): array
    {
        $business = $this->resource;
        $launchControl = app(LaunchControlService::class);
        $readiness = $this->experienceService->calculateReadiness($business);
        $primaryAction = $this->experienceService->getPrimaryAction($business);

        $capabilities = [
            'catalog' => $launchControl->moduleEnabled('catalog') && $business->hasModule('catalog'),
            'orders' => $launchControl->moduleEnabled('orders') && $business->hasModule('orders'),
            'bookings' => $launchControl->moduleEnabled('bookings') && $business->hasModule('bookings'),
            'inventory' => $launchControl->moduleEnabled('inventory') && $business->hasModule('inventory'),
            'transport' => $launchControl->moduleEnabled('transport') && $business->hasModule('transport'),
            'turf' => $launchControl->moduleEnabled('turf') && $business->hasModule('turf'),
        ];

        $experiences = $launchControl->filterExperiences($business->enabled_experiences ?? ['directory']);
        $primaryExperience = in_array($business->primary_experience, $experiences, true)
            ? $business->primary_experience
            : ($experiences[0] ?? 'directory');
        if (! $launchControl->experienceEnabled($business->primary_experience ?? 'directory')) {
            $primaryAction = ['type' => 'contact', 'label' => 'Contact'];
        }
        $readiness = array_intersect_key($readiness, array_flip($experiences));

        return [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'description' => $business->description,
            'address' => $business->address,
            'locality' => $business->locality,
            'district' => $business->district,
            'pincode' => $business->pincode,
            'state' => $business->state,
            'latitude' => $business->latitude,
            'longitude' => $business->longitude,
            'phone' => $business->phone,
            'whatsapp' => $business->whatsapp,
            'email' => $business->email,
            'website' => $business->website,
            'photos' => $business->photos,
            'working_hours' => $business->working_hours,
            'is_active' => $business->is_active,
            'is_featured' => $business->is_featured,
            'average_rating' => $business->average_rating,
            'review_count' => $business->review_count,
            'quality_score' => $business->quality_score,
            'distance' => $business->distance,
            'category' => $business->relationLoaded('category') ? [
                'id' => $business->category->id,
                'name' => $business->category->name,
                'slug' => $business->category->slug,
                'icon' => $business->category->icon,
            ] : null,
            'capabilities' => $capabilities,
            'experiences' => $experiences,
            'primary_experience' => $primaryExperience,
            'readiness' => $readiness,
            'primary_action' => $primaryAction,
        ];
    }
}
