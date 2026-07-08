@extends('layouts.admin')

@section('title', 'Categories')
@section('header', 'Categories')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Categories</h3>
    <a href="{{ route('admin.categories.create') }}" class="btn-primary">+ Add Category</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Businesses</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($categories ?? [] as $category)
                <tr>
                    <td class="font-medium">{{ $category->name }}</td>
                    <td class="text-sm text-slate-400">{{ $category->slug }}</td>
                    <td class="text-sm">{{ $category->businesses_count ?? $category->businesses->count() }}</td>
                    <td>
                        @if($category->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('admin.categories.edit', $category->id) }}" class="text-blue-400 hover:text-blue-300">Edit</a>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category->id) }}" data-confirm="Delete this category?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-400">No categories yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
