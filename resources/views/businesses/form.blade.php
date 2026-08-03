@extends('vendor.layouts.dashboard')

@section('title', 'Edit Business')
@section('header', 'Edit Business')

@section('content')
<div class="max-w-3xl mx-auto">
    <form method="POST" action="{{ route('vendor.businesses.update', $business->id) }}" enctype="multipart/form-data">
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
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Basic Info</h4>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Business Name *</label>
                <input type="text" name="name" value="{{ old('name', $business->name ?? '') }}" required class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="3" class="input-dark">{{ old('description', $business->description ?? '') }}</textarea>
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Contact & Location</h4>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Address *</label>
                <input type="text" name="address" value="{{ old('address', $business->address ?? '') }}" required class="input-dark">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $business->phone ?? '') }}" class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">WhatsApp</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp', $business->whatsapp ?? '') }}" class="input-dark">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $business->email ?? '') }}" class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Website</label>
                    <input type="url" name="website" value="{{ old('website', $business->website ?? '') }}" class="input-dark">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Working Hours (JSON)</label>
                <textarea name="working_hours" rows="4" class="input-dark font-mono text-xs">{{ old('working_hours', is_string($business->working_hours ?? '') ? $business->working_hours : json_encode($business->working_hours ?? '', JSON_PRETTY_PRINT)) }}</textarea>
                <p class="text-slate-500 text-xs mt-1">e.g. {"monday":"9:00-18:00","tuesday":"9:00-18:00"}</p>
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Photos</h4>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Upload Photos</label>
                <input type="file" name="photos[]" multiple accept="image/*" class="input-dark">
                @if(isset($business) && !empty($business->photos))
                    <div class="flex gap-2 mt-3 flex-wrap">
                        @foreach($business->photos as $photo)
                            <div class="relative group">
                                <img src="{{ str_starts_with($photo, 'http') ? $photo : asset($photo) }}"
                                     class="w-20 h-20 object-cover rounded-lg border border-white/5">
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Options</h4>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $business->is_active ?? 1) ? 'checked' : '' }}>
                <span class="text-sm text-slate-300">Active</span>
            </label>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">Update</button>
            <a href="{{ route('vendor.businesses') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
