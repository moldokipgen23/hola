@extends('layouts.admin')

@section('title', 'Reviews')
@section('header', 'Review Moderation')

@section('content')
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search user or business..." class="input-dark w-full">
        </div>
        <div>
            <select name="rating" class="input-dark w-full">
                <option value="">All Ratings</option>
                @foreach(range(5, 1) as $r)
                    <option value="{{ $r }}" {{ request('rating') == $r ? 'selected' : '' }}>{{ $r }} Star{{ $r > 1 ? 's' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <input type="text" name="business_id" value="{{ request('business_id') }}" placeholder="Business ID..." class="input-dark w-full">
        </div>
        <div>
            <button type="submit" class="btn-primary px-6 w-full">Filter</button>
        </div>
    </div>
    <div class="flex gap-2 mt-3">
        <a href="{{ route('admin.reviews') }}" class="btn-ghost">Clear</a>
        <span class="text-slate-500 text-sm self-center ml-2">{{ $reviews->total() }} reviews</span>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Business</th>
                <th>Rating</th>
                <th>Comment</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reviews as $review)
                <tr>
                    <td class="text-sm">{{ $review->user->name ?? '-' }}</td>
                    <td class="text-sm">{{ $review->business->name ?? '-' }}</td>
                    <td>
                        <div class="flex items-center gap-0.5">
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= $review->rating)
                                    <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-slate-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endif
                            @endfor
                        </div>
                    </td>
                    <td class="text-sm max-w-xs truncate">{{ $review->comment ?? '-' }}</td>
                    <td>
                        @if($review->owner_response)
                            <span class="badge badge-green">Responded</span>
                        @else
                            <span class="badge badge-yellow">Pending</span>
                        @endif
                    </td>
                    <td class="text-sm text-slate-400">{{ $review->created_at->format('M d, Y') }}</td>
                    <td class="text-sm">
                        <form method="POST" action="{{ route('admin.reviews.destroy', $review->id) }}" data-confirm="Delete this review?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400 py-8">No reviews found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($reviews->hasPages())
    <div class="mt-6">{{ $reviews->withQueryString()->links() }}</div>
@endif
@endsection
