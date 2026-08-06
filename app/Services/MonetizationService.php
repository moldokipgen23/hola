<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Order;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;

class MonetizationService
{
    /**
     * The commission percent a business pays. A business-level override wins;
     * otherwise the active subscription plan's rate; otherwise the platform default.
     */
    public function commissionPercentFor(Business $business): float
    {
        if ($business->commission_percent !== null && (float) $business->commission_percent > 0) {
            return (float) $business->commission_percent;
        }

        $subscription = $business->subscription;
        if ($subscription && $subscription->isActive() && $subscription->plan) {
            return (float) $subscription->plan->commission_percent;
        }

        $default = (float) app(Setting::class)::get('platform_commission_percent', 0);

        return $default;
    }

    /**
     * Record a platform commission for a completed transaction.
     * Idempotent: a commission transaction per billable+type is only created once.
     */
    public function recordCommission(Business $business, string $billableType, int $billableId, float $grossAmount): ?Transaction
    {
        if ($grossAmount <= 0) {
            return null;
        }

        $percent = $this->commissionPercentFor($business);
        if ($percent <= 0) {
            return null;
        }

        $type = match ($billableType) {
            Order::class => 'order_commission',
            Booking::class => 'booking_commission',
            Trip::class => 'trip_commission',
            default => 'commission',
        };

        $exists = Transaction::where('billable_type', $billableType)
            ->where('billable_id', $billableId)
            ->where('type', $type)
            ->where('status', 'completed')
            ->exists();
        if ($exists) {
            return null;
        }

        $commission = round($grossAmount * ($percent / 100), 2);

        return Transaction::create([
            'user_id' => $business->created_by,
            'billable_type' => $billableType,
            'billable_id' => $billableId,
            'type' => $type,
            'amount' => $commission,
            'currency' => 'INR',
            'status' => 'completed',
            'payment_method' => 'platform',
            'metadata' => [
                'business_id' => $business->id,
                'commission_percent' => $percent,
                'gross_amount' => $grossAmount,
            ],
        ]);
    }

    /**
     * Platform earnings summary.
     */
    public function earnings(?string $from = null, ?string $to = null): array
    {
        $query = Transaction::whereIn('type', ['order_commission', 'booking_commission', 'trip_commission', 'commission'])
            ->where('status', 'completed');

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $rows = $query->get();

        $byType = $rows->groupBy('type')->map(fn ($group) => [
            'count' => $group->count(),
            'amount' => (float) $group->sum('amount'),
        ]);

        $byBusiness = $rows->groupBy(fn ($t) => $t->metadata['business_id'] ?? null)
            ->filter(fn ($group, $key) => $key !== null)
            ->map(function ($group, $businessId) {
                $business = Business::find($businessId);

                return [
                    'business_id' => $businessId,
                    'business_name' => $business?->name ?? 'Unknown',
                    'count' => $group->count(),
                    'amount' => (float) $group->sum('amount'),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return [
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
            'by_type' => $byType,
            'by_business' => $byBusiness,
        ];
    }

    /**
     * Subscribe a business to a plan.
     */
    public function subscribe(Business $business, SubscriptionPlan $plan, ?string $status = 'active'): BusinessSubscription
    {
        return DB::transaction(function () use ($business, $plan, $status) {
            $business->subscriptions()->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $interval = $plan->billing_interval === 'yearly' ? 'year' : 'month';

            return BusinessSubscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'starts_at' => now(),
                'ends_at' => now()->add(1, $interval),
                'trial_ends_at' => $status === 'trialing' ? now()->addDays(7) : null,
            ]);
        });
    }
}
