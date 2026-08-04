@extends('layouts.admin')

@section('title', 'Product Categories')
@section('header', 'Product Categories')

@section('content')
@php
    $roots = $categories->whereNull('parent_id');
    $childrenByParent = $categories->whereNotNull('parent_id')->groupBy('parent_id');
@endphp
<div class="max-w-6xl mx-auto">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-slate-400 text-sm">Categories and sub-categories per shopping business type.</p>
            <p class="text-slate-500 text-xs mt-1">Pick a business type to manage its product categories.</p>
        </div>
    </div>

    <!-- Business type tabs -->
    <div class="flex gap-1 mb-6 border-b border-white/10 overflow-x-auto">
        @foreach($businessTypes as $bt)
            <a href="{{ route('admin.product-categories', ['business_type_id' => $bt->id]) }}"
                class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ $type && $type->id === $bt->id ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
                {{ $bt->name }}
            </a>
        @endforeach
    </div>

    @if(! $type)
        <div class="glass-card p-4 rounded-lg text-center">
            <p class="text-slate-500 text-sm">No shopping business types yet.</p>
        </div>
    @else
        <!-- Add category -->
        <div class="glass-card p-5 rounded-xl mb-6">
            <h3 class="text-white font-semibold mb-1">Add Product Category</h3>
            <p class="text-slate-500 text-xs mb-4">For: <span class="text-slate-300">{{ $type->name }}</span></p>
            <form method="POST" action="{{ route('admin.product-categories.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                @csrf
                <input type="hidden" name="business_type_id" value="{{ $type->id }}">
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Name</label>
                    <input type="text" name="name" required class="input-dark" placeholder="e.g. Medicines, Fruits">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Parent Category</label>
                    <select name="parent_id" class="input-dark">
                        <option value="">None (top level)</option>
                        @foreach($roots as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Shop Section</label>
                    <select name="shop_section_id" class="input-dark">
                        <option value="">None</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary">Add</button>
            </form>
        </div>

        <!-- List (tree) -->
        <div class="glass-card rounded-xl overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Shop Section</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roots as $root)
                        <tr>
                            <td class="font-semibold">{{ $root->name }}</td>
                            <td>
                                @if($root->section)
                                    <span class="badge badge-blue">{{ $root->section->name }}</span>
                                @else
                                    <span class="text-slate-500 text-sm">—</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $root->products_count }}</td>
                            <td>
                                @if($root->is_active)
                                    <span class="badge badge-green">Active</span>
                                @else
                                    <span class="badge badge-red">Inactive</span>
                                @endif
                            </td>
                            <td class="text-sm">
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('admin.product-categories.update', $root->id) }}" class="flex items-center gap-2">
                                        @csrf @method('PATCH')
                                        <input type="text" name="name" value="{{ $root->name }}" class="input-dark !py-1.5 text-sm w-32" required>
                                        <select name="parent_id" class="input-dark !py-1.5 text-sm">
                                            <option value="">Top level</option>
                                            @foreach($roots as $opt)
                                                @if($opt->id !== $root->id)
                                                    <option value="{{ $opt->id }}" {{ $root->parent_id === $opt->id ? 'selected' : '' }}>{{ $opt->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <select name="shop_section_id" class="input-dark !py-1.5 text-sm">
                                            <option value="">No section</option>
                                            @foreach($sections as $section)
                                                <option value="{{ $section->id }}" {{ $root->shop_section_id === $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                                            @endforeach
                                        </select>
                                        <label class="flex items-center gap-1 text-xs text-slate-400">
                                            <input type="checkbox" name="is_active" value="1" {{ $root->is_active ? 'checked' : '' }} class="rounded">
                                            Active
                                        </label>
                                        <button type="submit" class="text-blue-400 hover:text-blue-300 text-xs">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.product-categories.destroy', $root->id) }}" data-confirm="Delete this product category?" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-300 text-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @foreach($childrenByParent->get($root->id, collect()) as $child)
                            <tr class="bg-white/[0.02]">
                                <td class="font-medium">
                                    <span class="text-slate-600 mr-2">└</span>{{ $child->name }}
                                </td>
                                <td>
                                    @if($child->section)
                                        <span class="badge badge-blue">{{ $child->section->name }}</span>
                                    @else
                                        <span class="text-slate-500 text-sm">—</span>
                                    @endif
                                </td>
                                <td class="text-sm">{{ $child->products_count }}</td>
                                <td>
                                    @if($child->is_active)
                                        <span class="badge badge-green">Active</span>
                                    @else
                                        <span class="badge badge-red">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-sm">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('admin.product-categories.update', $child->id) }}" class="flex items-center gap-2">
                                            @csrf @method('PATCH')
                                            <input type="text" name="name" value="{{ $child->name }}" class="input-dark !py-1.5 text-sm w-32" required>
                                            <select name="parent_id" class="input-dark !py-1.5 text-sm">
                                                <option value="">Top level</option>
                                                @foreach($roots as $opt)
                                                    <option value="{{ $opt->id }}" {{ $child->parent_id === $opt->id ? 'selected' : '' }}>{{ $opt->name }}</option>
                                                @endforeach
                                            </select>
                                            <select name="shop_section_id" class="input-dark !py-1.5 text-sm">
                                                <option value="">No section</option>
                                                @foreach($sections as $section)
                                                    <option value="{{ $section->id }}" {{ $child->shop_section_id === $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                                                @endforeach
                                            </select>
                                            <label class="flex items-center gap-1 text-xs text-slate-400">
                                                <input type="checkbox" name="is_active" value="1" {{ $child->is_active ? 'checked' : '' }} class="rounded">
                                                Active
                                            </label>
                                            <button type="submit" class="text-blue-400 hover:text-blue-300 text-xs">Save</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.product-categories.destroy', $child->id) }}" data-confirm="Delete this product category?" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-300 text-xs">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-slate-400 py-8">No product categories yet for {{ $type->name }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
