@extends('layouts.admin')

@section('title', 'Reports')
@section('header', 'Reports')

@section('content')
<!-- Filters -->
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="reviewed" {{ request('status') == 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
            </select>
        </div>
        <div>
            <select name="type" class="input-dark w-full">
                <option value="">All Types</option>
                <option value="spam" {{ request('type') == 'spam' ? 'selected' : '' }}>Spam</option>
                <option value="wrong_info" {{ request('type') == 'wrong_info' ? 'selected' : '' }}>Wrong Info</option>
                <option value="closed" {{ request('type') == 'closed' ? 'selected' : '' }}>Closed</option>
                <option value="other" {{ request('type') == 'other' ? 'selected' : '' }}>Other</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary px-6 w-full">Filter</button>
        </div>
        <div>
            <a href="{{ route('admin.reports') }}" class="btn-ghost w-full text-center">Clear</a>
        </div>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Business</th>
                <th>Type</th>
                <th>Message</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports ?? [] as $report)
                <tr>
                    <td class="text-sm">{{ $report->user->name ?? '-' }}</td>
                    <td class="text-sm">{{ $report->business->name ?? '-' }}</td>
                    <td class="text-sm">{{ str_replace('_', ' ', ucfirst($report->type)) }}</td>
                    <td class="text-sm max-w-xs truncate">{{ $report->message ?? '-' }}</td>
                    <td>
                        @if($report->status === 'pending')
                            <span class="badge badge-yellow">Pending</span>
                        @elseif($report->status === 'reviewed')
                            <span class="badge badge-blue">Reviewed</span>
                        @else
                            <span class="badge badge-green">Resolved</span>
                        @endif
                    </td>
                    <td class="text-sm text-slate-400">{{ $report->created_at->format('M d, Y') }}</td>
                    <td class="text-sm space-x-2">
                        @if($report->status !== 'resolved')
                            <form method="POST" action="{{ route('admin.reports.resolve', $report->id) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-green-400 hover:text-green-300">Resolve</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400">No reports.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($reports) && $reports->hasPages())
    <div class="mt-4 text-slate-400">{{ $reports->links() }}</div>
@endif
@endsection
