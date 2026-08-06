<?php

namespace App\Services;

use App\Models\Business;
use App\Models\SubscriptionPlan;

/**
 * Gates business capabilities behind their subscription plan.
 *
 * Plan `features` use machine keys:
 *   - directory          : directory listing (always on, free tier)
 *   - bookings           : in-app booking (appointment/stay/turf/seat)
 *   - shopping           : catalog + COD orders
 *   - transport          : taxi/seat/rental
 *   - unlimited_services : no cap on active services
 *   - featured           : featured placement in discovery
 *   - priority_support   : priority support flag
 *
 * Service caps (used with unlimited_services OFF):
 *   - max_active_services : Free = 5
 */
class PlanGate
{
    public const FEATURES = [
        'directory' => 'Directory listing',
        'bookings' => 'In-app booking',
        'shopping' => 'Shopping & COD orders',
        'transport' => 'Transport & rides',
        'unlimited_services' => 'Unlimited services',
        'featured' => 'Featured placement',
        'priority_support' => 'Priority support',
    ];

    public const DEFAULT_MAX_SERVICES = 5;

    /**
     * The plan that applies to a business (active subscription, else the free default).
     */
    public function planFor(Business $business): SubscriptionPlan
    {
        $subscription = $business->subscription;
        if ($subscription && $subscription->isActive() && $subscription->plan) {
            return $subscription->plan;
        }

        return SubscriptionPlan::where('slug', 'free')->first() ?? $this->freeFallback();
    }

    private function freeFallback(): SubscriptionPlan
    {
        return SubscriptionPlan::firstOrCreate(
            ['slug' => 'free'],
            ['name' => 'Free', 'price' => 0, 'commission_percent' => 0, 'is_active' => true],
        );
    }

    /**
     * Can this business use the given feature key?
     */
    public function can(Business $business, string $feature): bool
    {
        $plan = $this->planFor($business);
        $features = $plan->features ?? [];

        return in_array($feature, $features, true);
    }

    /**
     * Max active services for a business (unlimited when the plan allows).
     */
    public function maxActiveServices(Business $business): ?int
    {
        if ($this->can($business, 'unlimited_services')) {
            return null;
        }

        return self::DEFAULT_MAX_SERVICES;
    }

    /**
     * Enforce service caps: returns true if the business may add another service.
     */
    public function canAddService(Business $business): bool
    {
        $max = $this->maxActiveServices($business);
        if ($max === null) {
            return true;
        }

        $active = $business->services()->where('is_active', true)->count();

        return $active < $max;
    }
}
