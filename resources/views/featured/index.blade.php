@extends('layouts.admin')

@section('title', 'Featured Businesses')
@section('header', 'Featured Businesses')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Featured Businesses</h3>
    <a href="{{ route('admin.businesses') }}" class="text-blue-400 hover:text-blue-300">View All Businesses</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Views</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($featured ?? [] as $business)
                <tr>
                    <td class="font-medium">{{ $business->name }}</td>
                    <td class="text-sm">{{ $business->category->name ?? '-' }}</td>
                    <td class="text-sm">{{ number_format($business->views_count) }}</td>
                    <td class="text-sm">
                        <form method="POST" action="{{ route('admin.businesses.toggle', $business->id) }}" class="inline">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_featured" value="0">
                            <button type="submit" class="text-red-400 hover:text-red-300">Remove Featured</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-slate-400">No featured businesses.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
