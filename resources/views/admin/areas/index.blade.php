@extends('layouts.admin')

@section('title', 'Areas')
@section('header', 'Areas')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Areas</h3>
    <a href="{{ route('admin.areas.create') }}" class="btn-primary">+ Add Area</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>District</th>
                <th>State</th>
                <th>Businesses</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($areas ?? [] as $area)
                <tr>
                    <td class="font-medium">{{ $area->name }}</td>
                    <td class="text-sm text-slate-400">{{ $area->slug }}</td>
                    <td class="text-sm">{{ $area->district }}</td>
                    <td class="text-sm">{{ $area->state }}</td>
                    <td class="text-sm">{{ $area->businesses_count ?? $area->businesses->count() }}</td>
                    <td>
                        @if($area->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('admin.areas.edit', $area->id) }}" class="text-blue-400 hover:text-blue-300">Edit</a>
                        <form method="POST" action="{{ route('admin.areas.destroy', $area->id) }}" data-confirm="Delete this area?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400">No areas yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $areas->withQueryString()->links() }}
</div>
@endsection
