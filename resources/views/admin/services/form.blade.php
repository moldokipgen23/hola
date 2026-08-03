@extends('layouts.admin')

@section('title', 'Edit Service')
@section('header', 'Edit Service')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ route('admin.services.update', $service->id) }}">
        @csrf @method('PUT')

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
                <input type="text" value="{{ $service->business->name ?? 'N/A' }}" class="input-dark" disabled>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Name *</label>
                <input type="text" name="name" value="{{ old('name', $service->name) }}" required class="input-dark">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="3" class="input-dark">{{ old('description', $service->description) }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Price * (₹)</label>
                    <input type="number" name="price" value="{{ old('price', $service->price) }}" step="0.01" min="0" required class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Duration * (minutes)</label>
                    <input type="number" name="duration" value="{{ old('duration', $service->duration) }}" min="15" required class="input-dark">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Capacity</label>
                <input type="number" name="capacity" value="{{ old('capacity', $service->capacity) }}" min="1" class="input-dark" placeholder="Leave empty for unlimited">
            </div>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $service->is_active ?? 1) ? 'checked' : '' }}>
                <span class="text-sm text-slate-300">Active</span>
            </label>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">Update</button>
            <a href="{{ route('admin.services') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
