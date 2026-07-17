@extends('vendor.layouts.dashboard')

@section('title', isset($service) ? 'Edit Service' : 'Add Service')
@section('header', isset($service) ? 'Edit Service' : 'Add Service')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ isset($service) ? route('vendor.services.update', ['businessId' => $business->id, 'id' => $service->id]) : route('vendor.services.store', $business->id) }}">
        @csrf
        @if(isset($service)) @method('PUT') @endif

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="glass-card p-6 rounded-lg space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Business</label>
                <input type="text" value="{{ $business->name }}" disabled class="input-dark opacity-60">
                <input type="hidden" name="business_id" value="{{ $business->id }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Name *</label>
                <input type="text" name="name" value="{{ old('name', $service->name ?? '') }}" required class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="3" class="input-dark">{{ old('description', $service->description ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Price * ($)</label>
                    <input type="number" name="price" value="{{ old('price', $service->price ?? '') }}" step="0.01" min="0" required class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Duration * (minutes)</label>
                    <input type="number" name="duration" value="{{ old('duration', $service->duration ?? '') }}" min="15" required class="input-dark">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Capacity</label>
                <input type="number" name="capacity" value="{{ old('capacity', $service->capacity ?? '') }}" min="1" class="input-dark" placeholder="Leave empty for unlimited">
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $service->is_active ?? 1) ? 'checked' : '' }}>
                <span class="text-sm text-slate-300">Active</span>
            </label>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">{{ isset($service) ? 'Update' : 'Create' }}</button>
            <a href="{{ route('vendor.services', $business->id) }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
