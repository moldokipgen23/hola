@extends('layouts.admin')

@section('title', 'Claims')
@section('header', 'Claims')

@section('content')
<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Business</th>
                <th>Message</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($claims ?? [] as $claim)
                <tr>
                    <td>
                        <div class="text-sm">{{ $claim->user->name ?? '-' }}</div>
                        <div class="text-xs text-slate-500">{{ $claim->user->email ?? '' }}</div>
                    </td>
                    <td class="text-sm">{{ $claim->business->name ?? '-' }}</td>
                    <td class="text-sm max-w-xs truncate">{{ $claim->message ?? '-' }}</td>
                    <td>
                        @if($claim->status === 'pending')
                            <span class="badge badge-yellow">Pending</span>
                        @elseif($claim->status === 'approved')
                            <span class="badge badge-green">Approved</span>
                        @else
                            <span class="badge badge-red">Rejected</span>
                        @endif
                    </td>
                    <td class="text-sm text-slate-400">{{ $claim->created_at->format('M d, Y') }}</td>
                    <td class="text-sm space-x-2">
                        @if($claim->status === 'pending')
                            <form method="POST" action="{{ route('admin.claims.approve', $claim->id) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-green-400 hover:text-green-300">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.claims.reject', $claim->id) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-red-400 hover:text-red-300">Reject</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400">No claims.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($claims) && $claims->hasPages())
    <div class="mt-4 text-slate-400">{{ $claims->links() }}</div>
@endif
@endsection
