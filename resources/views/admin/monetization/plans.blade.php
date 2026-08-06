@extends('layouts.admin')

@section('title', 'Subscription Plans')
@section('header', 'Subscription Plans')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h3 class="text-white font-semibold text-lg">Monetization — Plans</h3>
        <p class="text-slate-500 text-sm mt-1">Vendor subscription tiers. The plan's commission percent is the platform fee charged on that business's completed orders/bookings/trips.</p>
    </div>
    <button onclick="document.getElementById('createForm').classList.toggle('hidden')" class="btn-primary">+ New plan</button>
</div>

@if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
@endif

<form id="createForm" method="POST" action="{{ route('admin.subscription-plans.store') }}" class="glass-card p-5 rounded-xl mb-6 hidden">
    @csrf
    <h4 class="text-white font-medium mb-4">Create plan</h4>
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <input type="text" name="name" placeholder="Name" required class="input-dark">
        <input type="text" name="slug" placeholder="Slug (e.g. premium)" required class="input-dark">
        <select name="billing_interval" class="input-dark"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select>
        <input type="number" name="price" placeholder="Price ₹" min="0" step="0.01" required class="input-dark">
        <input type="number" name="commission_percent" placeholder="Commission %" min="0" max="100" step="0.01" required class="input-dark">
        <div class="flex gap-2">
            <button class="btn-primary">Create</button>
            <label class="flex items-center gap-2 text-xs text-slate-300"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        </div>
        <input type="text" name="features" placeholder="Features (comma separated)" class="input-dark md:col-span-6">
    </div>
</form>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
@forelse($plans as $plan)
    <div class="glass-card p-5 rounded-xl">
        <form method="POST" action="{{ route('admin.subscription-plans.update', $plan->id) }}">
            @csrf @method('PUT')
            <div class="flex justify-between items-start mb-3">
                <input type="text" name="name" value="{{ $plan->name }}" class="input-dark font-semibold">
                <span class="text-xs {{ $plan->is_active ? 'badge-green' : 'badge-yellow' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <input type="number" name="price" value="{{ $plan->price }}" min="0" step="0.01" class="input-dark" aria-label="Price">
                <input type="number" name="commission_percent" value="{{ $plan->commission_percent }}" min="0" max="100" step="0.01" class="input-dark" aria-label="Commission %">
            </div>
            <select name="billing_interval" class="input-dark w-full mb-2">
                <option value="monthly" {{ $plan->billing_interval === 'monthly' ? 'selected' : '' }}>Monthly</option>
                <option value="yearly" {{ $plan->billing_interval === 'yearly' ? 'selected' : '' }}>Yearly</option>
            </select>
            <input type="text" name="features" value="{{ is_array($plan->features) ? implode(', ', $plan->features) : $plan->features }}" placeholder="Features" class="input-dark w-full mb-2">
            <label class="flex items-center gap-2 text-xs text-slate-300 mb-3"><input type="checkbox" name="is_active" value="1" {{ $plan->is_active ? 'checked' : '' }}> Active</label>
            <div class="flex gap-2">
                <button class="btn-primary text-xs px-3 py-2">Save</button>
                <button type="submit" form="delete-plan-{{ $plan->id }}" class="text-red-400 text-sm" data-confirm="Delete this plan?">Delete</button>
            </div>
        </form>
        <form id="delete-plan-{{ $plan->id }}" method="POST" action="{{ route('admin.subscription-plans.destroy', $plan->id) }}">@csrf @method('DELETE')</form>
    </div>
@empty
    <div class="glass-card p-10 rounded-xl text-center text-slate-500 md:col-span-3">No plans yet. Create a Free (0%) plan and a paid tier to start.</div>
@endforelse
</div>

<div class="mt-6 glass-card p-5 rounded-xl">
    <h4 class="text-white font-medium mb-2">Platform default commission</h4>
    <form method="POST" action="{{ route('admin.settings.update') }}" class="flex gap-3 items-end">
        @csrf @method('PUT')
        <div>
            <label class="block text-xs text-slate-400 mb-1">Default commission % for businesses with no plan/override</label>
            <input type="number" name="settings[platform_commission_percent]" value="{{ \App\Models\Setting::get('platform_commission_percent', 0) }}" min="0" max="100" step="0.01" class="input-dark">
        </div>
        <button class="btn-primary">Save default</button>
    </form>
</div>
@endsection
