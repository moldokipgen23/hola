@extends('vendor.layouts.dashboard')

@section('title', isset($zone) ? 'Edit Delivery Zone' : 'Add Delivery Zone')
@section('header', isset($zone) ? 'Edit Delivery Zone' : 'Add Delivery Zone')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ isset($zone) ? route('vendor.delivery-zones.update', $zone->id) : route('vendor.delivery-zones.store') }}">
        @csrf
        @if(isset($zone)) @method('PUT') @endif

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <input type="hidden" name="business_id" value="{{ $business->id }}">

        <div class="glass-card p-6 rounded-lg space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Business</label>
                <p class="text-white font-medium">{{ $business->name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Area *</label>
                <select name="area_id" required class="input-dark">
                    <option value="">Select area...</option>
                    @foreach($areas ?? [] as $area)
                        <option value="{{ $area->id }}" {{ old('area_id', $zone->area_id ?? '') == $area->id ? 'selected' : '' }}>{{ $area->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Delivery Fee (₹) *</label>
                    <input type="number" name="delivery_fee" value="{{ old('delivery_fee', $zone->delivery_fee ?? '20') }}" step="0.01" min="0" required class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Min Order Amount (₹)</label>
                    <input type="number" name="min_order_amount" value="{{ old('min_order_amount', $zone->min_order_amount ?? '') }}" step="0.01" min="0" class="input-dark" placeholder="Leave empty for no minimum">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Estimated Delivery Time (minutes)</label>
                <input type="number" name="estimated_minutes" value="{{ old('estimated_minutes', $zone->estimated_minutes ?? '30') }}" min="1" class="input-dark">
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $zone->is_active ?? 1) ? 'checked' : '' }}>
                <span class="text-sm text-slate-300">Active</span>
            </label>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">{{ isset($zone) ? 'Update' : 'Create' }}</button>
            <a href="{{ route('vendor.delivery-zones') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
