@extends('layouts.admin')

@section('title', 'Subcategories')
@section('header', 'Subcategories')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Subcategories</h3>
    <a href="{{ route('admin.subcategories.create') }}" class="btn-primary">+ Add Subcategory</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($subcategories ?? [] as $sub)
                <tr>
                    <td class="font-medium">{{ $sub->name }}</td>
                    <td class="text-sm">{{ $sub->category->name ?? '-' }}</td>
                    <td class="text-sm text-slate-400">{{ $sub->slug }}</td>
                    <td>
                        @if($sub->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('admin.subcategories.edit', $sub->id) }}" class="text-blue-400 hover:text-blue-300">Edit</a>
                        <form method="POST" action="{{ route('admin.subcategories.destroy', $sub->id) }}" data-confirm="Delete?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-400">No subcategories yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($subcategories) && $subcategories->hasPages())
    <div class="mt-4 text-slate-400">{{ $subcategories->links() }}</div>
@endif
@endsection
