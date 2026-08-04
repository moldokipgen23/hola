@extends('layouts.admin')

@section('title', 'Shop Sections')
@section('header', 'Shop Sections')

@section('content')
<div class="max-w-6xl mx-auto">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-slate-400 text-sm">Global storefront sections for the Shop department (e.g. Grocery, Food, Medicine, Shopping).</p>
            <p class="text-slate-500 text-xs mt-1">These are independent from Directory business classifications. Each business assigns its own product categories to a section.</p>
        </div>
    </div>

    <!-- Add Section -->
    <div class="glass-card p-5 rounded-xl mb-6">
        <h3 class="text-white font-semibold mb-4">Add Shop Section</h3>
        <form method="POST" action="{{ route('admin.shop-sections.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm text-slate-400 mb-1">Name</label>
                <input type="text" name="name" required class="input-dark" placeholder="e.g. Grocery, Food, Medicine, Shopping">
            </div>
            <div class="flex-1 min-w-[220px]">
                <label class="block text-sm text-slate-400 mb-1">Description</label>
                <input type="text" name="description" class="input-dark" placeholder="What belongs in this section?">
            </div>
            <div class="w-28">
                <label class="block text-sm text-slate-400 mb-1">Sort Order</label>
                <input type="number" name="sort_order" value="0" min="0" class="input-dark">
            </div>
            <button type="submit" class="btn-primary">Add</button>
        </form>
    </div>

    <!-- Sections -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($sections as $section)
            <div class="glass-card p-5 rounded-xl">
                <div class="flex items-start justify-between mb-2">
                    <h4 class="text-white font-semibold text-lg">{{ $section->name }}</h4>
                    @if($section->is_active)
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="badge badge-red">Inactive</span>
                    @endif
                </div>
                <p class="text-slate-500 text-sm mb-1">{{ $section->description ?? 'No description' }}</p>
                <p class="text-slate-500 text-xs mb-4">{{ $section->product_categories_count ?? 0 }} product categories</p>
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('admin.shop-sections.toggle', $section->id) }}" class="inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-sm text-slate-400 hover:text-white">{{ $section->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.shop-sections.destroy', $section->id) }}" class="inline" data-confirm="Delete this shop section?">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm text-red-400 hover:text-red-300">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="glass-card p-4 rounded-lg text-center col-span-full">
                <p class="text-slate-500 text-sm">No shop sections yet.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
