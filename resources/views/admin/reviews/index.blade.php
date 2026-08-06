@extends('layouts.admin')

@section('title', 'Reviews')
@section('header', 'Review Moderation')

@section('content')
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
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
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="hidden" {{ request('status') == 'hidden' ? 'selected' : '' }}>Hidden</option>
            </select>
        </div>
        <div>
            <input type="text" name="business_id" value="{{ request('business_id') }}" placeholder="Business ID..." class="input-dark w-full">
        </div>
        <div>
            <button type="submit" class="btn-primary px-6 w-full">Filter</button>
        </div>
    </div>
    <div class="flex gap-3 mt-3 flex-wrap">
        <a href="{{ route('admin.reviews') }}" class="btn-ghost">Clear</a>
        <a href="{{ route('admin.reviews', ['status' => 'pending']) }}" class="badge {{ request('status') == 'pending' ? 'badge-yellow' : 'bg-white/5 text-slate-400' }}">Pending: {{ $counts['pending'] }}</a>
        <a href="{{ route('admin.reviews', ['status' => 'approved']) }}" class="badge {{ request('status') == 'approved' ? 'badge-green' : 'bg-white/5 text-slate-400' }}">Approved: {{ $counts['approved'] }}</a>
        <a href="{{ route('admin.reviews', ['status' => 'hidden']) }}" class="badge {{ request('status') == 'hidden' ? 'badge-red' : 'bg-white/5 text-slate-400' }}">Hidden: {{ $counts['hidden'] }}</a>
        <span class="text-slate-500 text-sm self-center">{{ $reviews->total() }} reviews</span>
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
                <th>Photo</th>
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
                    <td class="text-sm max-w-xs truncate">{{ $review->comment ?? '-' }}
                        @if($review->moderation_reason)
                            <div class="text-xs text-amber-400 mt-1">Reason: {{ $review->moderation_reason }}</div>
                        @endif
                    </td>
                    <td>
                        @if($review->photo)
                            <img src="{{ Storage::url($review->photo) }}" class="w-10 h-10 rounded object-cover" alt="Review photo">
                        @else
                            <span class="text-slate-600 text-xs">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusColors = [
                                'approved' => 'badge-green',
                                'pending' => 'badge-yellow',
                                'hidden' => 'badge-red',
                            ];
                        @endphp
                        <span class="badge {{ $statusColors[$review->status] ?? 'badge-yellow' }}">{{ ucfirst($review->status) }}</span>
                        @if($review->flagged_at)
                            <div class="text-xs text-red-400 mt-1">Flagged {{ $review->flagged_at->format('M d') }}</div>
                        @endif
                    </td>
                    <td class="text-sm text-slate-400">{{ $review->created_at->format('M d, Y') }}</td>
                    <td class="text-sm">
                        <div class="flex gap-2 flex-wrap">
                            @if($review->status !== 'approved')
                            <form method="POST" action="{{ route('admin.reviews.moderate', $review->id) }}" class="inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="text-green-400 hover:text-green-300">Approve</button>
                            </form>
                            @endif
                            @if($review->status !== 'hidden')
                            <form method="POST" action="{{ route('admin.reviews.moderate', $review->id) }}" class="inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="status" value="hidden">
                                <input type="text" name="reason" placeholder="Reason..." class="input-dark w-40 py-1 text-xs">
                                <button type="submit" class="text-amber-400 hover:text-amber-300">Hide</button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('admin.reviews.destroy', $review->id) }}" data-confirm="Delete this review?" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-slate-400 py-8">No reviews found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($reviews->hasPages())
    <div class="mt-6">{{ $reviews->withQueryString()->links() }}</div>
@endif
@endsection