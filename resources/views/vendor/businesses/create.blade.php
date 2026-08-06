@extends('vendor.layouts.dashboard')

@section('title', 'Add Business')
@section('header', 'Add Business')

@section('content')
<div class="max-w-3xl mx-auto">
    <form method="POST" action="{{ route('vendor.businesses.store') }}" enctype="multipart/form-data">
        @csrf

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
                <input type="text" name="name" value="{{ old('name') }}" required class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Category *</label>
                <select name="category_id" required class="input-dark">
                    <option value="">Select a category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->parent ? $category->parent->name.' → ' : '' }}{{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-slate-500 text-xs mt-1">Customers find you through this category. You can change it later.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="3" class="input-dark">{{ old('description') }}</textarea>
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Contact & Location</h4>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Address *</label>
                <input type="text" name="address" value="{{ old('address') }}" required class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">City *</label>
                <select name="city_id" required class="input-dark">
                    <option value="">Select city</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}" {{ old('city_id') == $city->id ? 'selected' : '' }}>
                            {{ $city->name }}{{ $city->state ? ' — '.$city->state : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-1">Applies to Discovery & Booking. Shopping is available in Lamka only.</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">WhatsApp</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" class="input-dark">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="input-dark">
            </div>
        </div>

        <div class="glass-card p-5 rounded-lg mt-5 flex justify-between items-center">
            <p class="text-xs text-slate-500">Your listing appears after an administrator verifies it.</p>
            <div class="flex gap-2">
                <a href="{{ route('vendor.businesses') }}" class="btn-ghost">Cancel</a>
                <button class="btn-primary">Create business</button>
            </div>
        </div>
    </form>
</div>
@endsection
