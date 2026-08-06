@extends('vendor.layouts.dashboard')

@section('title', 'Subscription & Plans')
@section('header', 'Subscription & Plans')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h3 class="text-white font-semibold text-lg">Choose a plan for {{ $business->name }}</h3>
        <p class="text-slate-500 text-sm mt-1">Your plan's commission rate is what the platform charges on your completed orders, bookings and trips.</p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
    @endif

    @if($business->subscription)
    <div class="glass-card p-5 rounded-xl mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <div>
                <span class="text-slate-500 text-xs block">Current plan</span>
                <span class="text-white font-semibold">{{ $business->subscription->plan?->name ?? 'None' }}</span>
            </div>
            <div>
                <span class="text-slate-500 text-xs block">Status</span>
                <span class="badge {{ $business->subscription->isActive() ? 'badge-green' : 'badge-yellow' }}">{{ $business->subscription->status }}</span>
            </div>
            <div>
                <span class="text-slate-500 text-xs block">Your commission rate</span>
                <span class="text-white font-semibold">{{ $monetization->commissionPercentFor($business) }}%</span>
            </div>
            <div>
                <span class="text-slate-500 text-xs block">Renews</span>
                <span class="text-white">{{ $business->subscription->ends_at?->format('M d, Y') ?? '—' }}</span>
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach($plans as $plan)
            <div class="glass-card p-6 rounded-xl flex flex-col">
                <h4 class="text-white font-semibold text-lg">{{ $plan->name }}</h4>
                <div class="mt-2 text-2xl font-bold text-white">₹{{ number_format($plan->price, 2) }}
                    <span class="text-sm text-slate-500">/{{ $plan->billing_interval === 'yearly' ? 'year' : 'month' }}</span>
                </div>
                <div class="mt-1 text-sm text-emerald-400">Platform commission: {{ $plan->commission_percent }}%</div>
                @if(is_array($plan->features) && count($plan->features))
                <ul class="mt-4 space-y-1 text-sm text-slate-300 flex-1">
                    @foreach($plan->features as $feature)
                        <li>• {{ $feature }}</li>
                    @endforeach
                </ul>
                @endif
                <form method="POST" action="{{ route('vendor.businesses.subscription.store', $business->id) }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <button class="btn-primary w-full">Choose this plan</button>
                </form>
            </div>
        @endforeach
    </div>

    @if($plans->isEmpty())
        <div class="glass-card p-10 rounded-xl text-center text-slate-500">No plans are available yet. Contact the platform.</div>
    @endif
</div>
@endsection
