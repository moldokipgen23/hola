<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'billing_interval' => 'monthly',
                'price' => 0,
                'commission_percent' => 0,
                'features' => ['directory'],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'billing_interval' => 'monthly',
                'price' => 299,
                'commission_percent' => 3,
                'features' => ['directory', 'bookings', 'unlimited_services'],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'billing_interval' => 'monthly',
                'price' => 799,
                'commission_percent' => 1,
                'features' => ['directory', 'bookings', 'shopping', 'transport', 'unlimited_services', 'featured', 'priority_support'],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
