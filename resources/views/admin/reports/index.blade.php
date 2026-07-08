@extends('layouts.admin')

@section('title', 'Reports')
@section('header', 'Reports')

@section('content')
<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Business</th>
                <th>Type</th>
                <th>Message</th>
                <th>Status</th>
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
                <tr><td colspan="6" class="text-center text-slate-400">No reports.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($reports) && $reports->hasPages())
    <div class="mt-4 text-slate-400">{{ $reports->links() }}</div>
@endif
@endsection
