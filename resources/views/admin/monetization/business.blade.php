@extends('layouts.admin')

@section('title', 'Business Monetization')
@section('header', 'Business Monetization')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h3 class="text-white font-semibold text-lg">{{ $business->name }}</h3>
        <p class="text-slate-500 text-sm mt-1">Set this business's subscription and commission.</p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
    @endif

    <div class="glass-card p-5 rounded-xl mb-6">
        <h4 class="text-white font-medium mb-4">Current</h4>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-white/5 rounded-lg p-3">
                <span class="text-slate-500 block text-xs">Plan</span>
                <span class="text-white">{{ $business->subscription?->plan?->name ?? 'No subscription' }}</span>
            </div>
            <div class="bg-white/5 rounded-lg p-3">
                <span class="text-slate-500 block text-xs">Status</span>
                <span class="text-white">{{ $business->subscription?->status ?? '—' }}</span>
            </div>
            <div class="bg-white/5 rounded-lg p-3">
                <span class="text-slate-500 block text-xs">Effective commission</span>
                <span class="text-white">{{ $service->commissionPercentFor($business) }}%</span>
            </div>
            <div class="bg-white/5 rounded-lg p-3">
                <span class="text-slate-500 block text-xs">Business override</span>
                <span class="text-white">{{ $business->commission_percent ?? 0 }}%</span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.businesses.monetization.update', $business->id) }}" class="glass-card p-5 rounded-xl">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm text-slate-400 mb-1">Plan</label>
                <select name="plan_id" class="input-dark w-full">
                    <option value="">No plan</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" {{ $business->subscription?->plan_id === $plan->id ? 'selected' : '' }}>
                            {{ $plan->name }} — {{ $plan->commission_percent }}%
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Status</label>
                <select name="subscription_status" class="input-dark w-full">
                    <option value="active">Active</option>
                    <option value="trialing">Trial</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Commission override %</label>
                <input type="number" name="commission_percent" value="{{ $business->commission_percent ?? 0 }}" min="0" max="100" step="0.01" class="input-dark w-full">
            </div>
        </div>
        <div class="mt-4 flex gap-2">
            <button class="btn-primary">Save</button>
            <a href="{{ route('admin.businesses') }}" class="btn-ghost">Back</a>
        </div>
    </form>
</div>
@endsection
