@extends('layouts.admin')

@section('title', isset($category) ? 'Edit Category' : 'Add Category')
@section('header', isset($category) ? 'Edit Category' : 'Add Category')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ isset($category) ? route('admin.categories.update', $category->id) : route('admin.categories.store') }}">
        @csrf
        @if(isset($category)) @method('PUT') @endif

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
                <label class="block text-sm font-medium text-slate-400 mb-1">Category name *</label>
                <input type="text" name="name" value="{{ old('name', $category->name ?? '') }}" required
                    class="input-dark" placeholder="Example: Taxi & Transport">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $category->slug ?? '') }}"
                    class="input-dark" placeholder="auto-generated if empty">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Icon (emoji or icon name)</label>
                <input type="text" name="icon" value="{{ old('icon', $category->icon ?? '') }}"
                    class="input-dark" placeholder="e.g., 🏪">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">Where should customers find it? *</label>
                <select name="module_type" required class="input-dark">
                    <option value="directory" {{ old('module_type', $category->module_type ?? request('module_type', '')) === 'directory' ? 'selected' : '' }}>Directory — listing, map, call, WhatsApp</option>
                    <option value="ordering" {{ old('module_type', $category->module_type ?? request('module_type', '')) === 'ordering' ? 'selected' : '' }}>Shopping — products/menu and COD orders</option>
                    <option value="booking" {{ old('module_type', $category->module_type ?? request('module_type', '')) === 'booking' ? 'selected' : '' }}>Booking — taxi, turf, stay, appointment</option>
                    <option value="both" {{ old('module_type', $category->module_type ?? request('module_type', '')) === 'both' ? 'selected' : '' }}>Both — only when a business genuinely needs shopping and booking</option>
                </select>
                <p class="text-xs text-slate-500 mt-2">Choose one simple path. You can add business types such as Taxi, Turf, Restaurant, or Grocery after saving.</p>
            </div>

            <div class="rounded-xl bg-white/[0.03] p-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $category->is_active ?? 1) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Show this category in Eiho One</span>
                </label>
            </div>

            <details class="text-sm text-slate-400">
                <summary class="cursor-pointer hover:text-white">Advanced options</summary>
                <div class="flex flex-wrap items-center gap-4 mt-3">
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $category->is_featured ?? 0) ? 'checked' : '' }}><span>Feature this category</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_canonical" value="1" {{ old('is_canonical', $category->is_canonical ?? 1) ? 'checked' : '' }}><span>Allow AI imports to use it</span></label>
                </div>
            </details>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Order</label>
                <input type="number" name="order" value="{{ old('order', $category->order ?? 0) }}"
                    class="input-dark">
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">
                {{ isset($category) ? 'Update' : 'Create' }}
            </button>
            <a href="{{ route('admin.categories') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
