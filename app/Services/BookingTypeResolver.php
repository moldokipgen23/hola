<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Category;

class BookingTypeResolver
{
    /**
     * Booking experience suited to a category. Falls back to appointment for
     * any generic booking business (barber, electrician, plumber, clinic,
     * tutor, gym, mechanic ...).
     */
    public function experienceForCategory(?Category $category): string
    {
        if (! $category) {
            return 'appointment';
        }

        $name = strtolower($category->name ?? '');
        $slug = strtolower($category->slug ?? '');

        $stay = ['hotel', 'lodge', 'guest house', 'homestay', 'resort', 'stay', 'property', 'inn'];
        foreach ($stay as $keyword) {
            if (str_contains($name, $keyword) || str_contains($slug, $keyword)) {
                return 'stay';
            }
        }

        $turf = ['turf', 'football', 'ground', 'court', 'sports', 'fitness', 'gym', 'swimming', 'playground'];
        foreach ($turf as $keyword) {
            if (str_contains($name, $keyword) || str_contains($slug, $keyword)) {
                return 'turf';
            }
        }

        $seat = ['event', 'cinema', 'theatre', 'stadium', 'auditorium', 'concert'];
        foreach ($seat as $keyword) {
            if (str_contains($name, $keyword) || str_contains($slug, $keyword)) {
                return 'seat_event';
            }
        }

        return 'appointment';
    }

    /**
     * Modules recommended for a booking business given its category.
     */
    public function modulesForCategory(?Category $category): array
    {
        $experience = $this->experienceForCategory($category);

        return match ($experience) {
            'turf' => ['bookings' => true, 'turf' => true],
            default => ['bookings' => true],
        };
    }

    /**
     * The full booking setup (modules + experiences) for a business.
     */
    public function setupFor(Business $business): array
    {
        $category = $business->category;
        $experience = $this->experienceForCategory($category);

        return [
            'modules' => $this->modulesForCategory($category),
            'experiences' => array_values(array_unique([$experience, 'directory'])),
            'experience' => $experience,
            'category_name' => $category?->name ?? 'Professional service',
        ];
    }
}
