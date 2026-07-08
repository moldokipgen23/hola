@extends('layouts.admin')

@section('title', 'Businesses')
@section('header', 'Businesses')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Businesses</h3>
    <a href="{{ route('admin.businesses.create') }}" class="btn-primary">+ Add Business</a>
</div>

<!-- Filters -->
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search businesses..."
                class="input-dark w-full">
        </div>
        <div>
            <select name="category" class="input-dark w-full">
                <option value="">All Categories</option>
                @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
                    <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div>
            <select name="featured" class="input-dark w-full">
                <option value="">All</option>
                <option value="1" {{ request('featured') == '1' ? 'selected' : '' }}>Featured</option>
                <option value="0" {{ request('featured') == '0' ? 'selected' : '' }}>Not Featured</option>
            </select>
        </div>
    </div>
    <div class="flex gap-2 mt-3">
        <button type="submit" class="btn-primary px-6">Filter</button>
        <a href="{{ route('admin.businesses') }}" class="btn-ghost">Clear</a>
        <span class="text-slate-500 text-sm self-center ml-2">{{ $businesses->total() }} results</span>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Status</th>
                <th>Featured</th>
                <th>Views</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($businesses ?? [] as $business)
                <tr>
                    <td class="font-medium">
                        <div>{{ $business->name }}</div>
                        <div class="text-xs text-slate-500">{{ $business->address }}</div>
                    </td>
                    <td class="text-sm">{{ $business->category->name ?? '-' }}</td>
                    <td>
                        @if($business->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td>
                        @if($business->is_featured)
                            <span class="badge badge-yellow">Featured</span>
                        @else
                            <span class="text-xs text-slate-500">-</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $business->views_count }}</td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('admin.businesses.show', $business->id) }}" class="text-emerald-400 hover:text-emerald-300">View</a>
                        <a href="{{ route('admin.businesses.edit', $business->id) }}" class="text-blue-400 hover:text-blue-300">Edit</a>
                        <form method="POST" action="{{ route('admin.businesses.destroy', $business->id) }}" data-confirm="Delete this business?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400">No businesses match your filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($businesses ?? collect(), 'links'))
    <div class="mt-4 text-slate-400">
        {{ $businesses->links() }}
    </div>
@endif
@endsection
