@extends('layouts.admin')

@section('title', isset($content) ? 'Edit Section' : 'Create Section')
@section('header', isset($content) ? 'Edit Homepage Section' : 'Create Homepage Section')

@section('content')
<div class="max-w-2xl">
    <div class="glass-card rounded-lg p-6">
        <form method="POST" action="{{ isset($content) ? route('admin.homepage.update', $content) : route('admin.homepage.store') }}">
            @csrf
            @if(isset($content))
                @method('PUT')
            @endif

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">World</label>
                    <select name="world_id" class="input-dark" required>
                        <option value="">Select World</option>
                        @foreach($worlds as $world)
                            <option value="{{ $world->id }}" {{ (old('world_id', $content->world_id ?? '') == $world->id) ? 'selected' : '' }}>
                                {{ $world->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('world_id') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Section Type</label>
                    <select name="section_type" class="input-dark" required>
                        <option value="hero" {{ (old('section_type', $content->section_type ?? '') === 'hero') ? 'selected' : '' }}>Hero</option>
                        <option value="featured" {{ (old('section_type', $content->section_type ?? '') === 'featured') ? 'selected' : '' }}>Featured</option>
                        <option value="categories" {{ (old('section_type', $content->section_type ?? '') === 'categories') ? 'selected' : '' }}>Categories</option>
                        <option value="promotions" {{ (old('section_type', $content->section_type ?? '') === 'promotions') ? 'selected' : '' }}>Promotions</option>
                    </select>
                    @error('section_type') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Title</label>
                    <input type="text" name="title" value="{{ old('title', $content->title ?? '') }}" class="input-dark" required>
                    @error('title') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Subtitle</label>
                    <input type="text" name="subtitle" value="{{ old('subtitle', $content->subtitle ?? '') }}" class="input-dark">
                    @error('subtitle') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Image URL</label>
                    <input type="url" name="image_url" value="{{ old('image_url', $content->image_url ?? '') }}" class="input-dark" placeholder="https://...">
                    @error('image_url') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Link URL</label>
                    <input type="url" name="link_url" value="{{ old('link_url', $content->link_url ?? '') }}" class="input-dark" placeholder="https://...">
                    @error('link_url') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $content->sort_order ?? 0) }}" class="input-dark" min="0">
                    @error('sort_order') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Meta (JSON)</label>
                    <textarea name="meta" class="input-dark" rows="3" placeholder='{"key": "value"}'>{{ old('meta', isset($content->meta) ? json_encode($content->meta) : '') }}</textarea>
                    @error('meta') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded" {{ old('is_active', $content->is_active ?? true) ? 'checked' : '' }}>
                    <label class="text-sm text-slate-300">Active</label>
                </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button type="submit" class="btn-primary">{{ isset($content) ? 'Update Section' : 'Create Section' }}</button>
                <a href="{{ route('admin.homepage') }}" class="btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
