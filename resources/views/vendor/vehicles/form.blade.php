@extends('vendor.layouts.dashboard')

@section('title', isset($vehicle) ? 'Edit Vehicle' : 'Add Vehicle')
@section('header', isset($vehicle) ? 'Edit Vehicle' : 'Add Vehicle')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ isset($vehicle) ? route('vendor.vehicles.update', $vehicle->id) : route('vendor.vehicles.store') }}" enctype="multipart/form-data">
        @csrf
        @if(isset($vehicle)) @method('PUT') @endif

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
                <label class="block text-sm font-medium text-slate-400 mb-1">Vehicle Name *</label>
                <input type="text" name="name" value="{{ old('name', $vehicle->name ?? '') }}" required class="input-dark" placeholder="e.g. Toyota Innova, Mahindra Bolero">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Type *</label>
                    <select name="type" required class="input-dark">
                        <option value="car" {{ old('type', $vehicle->type ?? '') === 'car' ? 'selected' : '' }}>Car</option>
                        <option value="bolero" {{ old('type', $vehicle->type ?? '') === 'bolero' ? 'selected' : '' }}>Bolero</option>
                        <option value="suv" {{ old('type', $vehicle->type ?? '') === 'suv' ? 'selected' : '' }}>SUV</option>
                        <option value="van" {{ old('type', $vehicle->type ?? '') === 'van' ? 'selected' : '' }}>Van</option>
                        <option value="auto" {{ old('type', $vehicle->type ?? '') === 'auto' ? 'selected' : '' }}>Auto</option>
                        <option value="bike" {{ old('type', $vehicle->type ?? '') === 'bike' ? 'selected' : '' }}>Bike</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Seats *</label>
                    <input type="number" name="seats" value="{{ old('seats', $vehicle->seats ?? '4') }}" min="1" max="50" required class="input-dark">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Base Fare (₹) *</label>
                    <input type="number" name="base_fare" value="{{ old('base_fare', $vehicle->base_fare ?? '') }}" step="0.01" min="0" required class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Fare Per KM (₹) *</label>
                    <input type="number" name="fare_per_km" value="{{ old('fare_per_km', $vehicle->fare_per_km ?? '') }}" step="0.01" min="0" required class="input-dark">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Min KM</label>
                    <input type="number" name="min_km" value="{{ old('min_km', $vehicle->min_km ?? '1') }}" min="1" class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Reg. Number</label>
                    <input type="text" name="registration_number" value="{{ old('registration_number', $vehicle->registration_number ?? '') }}" class="input-dark" placeholder="e.g. MN-01-AB-1234">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="3" class="input-dark">{{ old('description', $vehicle->description ?? '') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Image</label>
                <input type="file" name="image" accept="image/*" class="input-dark">
                @if(isset($vehicle) && $vehicle->image)
                    <div class="mt-2">
                        <img src="{{ str_starts_with($vehicle->image, 'http') ? $vehicle->image : asset($vehicle->image) }}" class="w-24 h-24 object-cover rounded-lg">
                    </div>
                @endif
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $vehicle->is_active ?? 1) ? 'checked' : '' }}>
                <span class="text-sm text-slate-300">Active</span>
            </label>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">{{ isset($vehicle) ? 'Update' : 'Create' }}</button>
            <a href="{{ route('vendor.vehicles') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
