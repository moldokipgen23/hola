@extends('layouts.admin')

@section('title', 'Businesses')
@section('header', 'Businesses')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Businesses</h3>
    <a href="{{ route('admin.businesses.create') }}" class="btn-primary">+ Add Business</a>
</div>

<form method="GET" class="mb-4 flex gap-2">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search businesses..."
        class="flex-1 input-dark">
    <button type="submit" class="btn-ghost">Search</button>
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
                        <a href="{{ route('admin.businesses.edit', $business->id) }}" class="text-blue-400 hover:text-blue-300">Edit</a>
                        <form method="POST" action="{{ route('admin.businesses.destroy', $business->id) }}" data-confirm="Delete this business?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400">No businesses yet.</td></tr>
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
