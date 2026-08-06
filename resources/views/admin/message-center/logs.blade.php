@extends('layouts.admin')

@section('title', 'Notification Log')
@section('header', 'Notification Log')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h3 class="text-white font-semibold text-lg">Claim invitation log</h3>
        <p class="text-slate-500 text-sm mt-1">Every notification attempt to a business.</p>
    </div>
    <a href="{{ route('admin.message-center') }}" class="btn-ghost">Back to Message Center</a>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="flex gap-3 items-center">
        <select name="status" class="input-dark">
            <option value="">All statuses</option>
            <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
        </select>
        <button class="btn-primary px-6">Filter</button>
        <a href="{{ route('admin.message-center.logs') }}" class="btn-ghost">Clear</a>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Business</th>
                <th>Channel</th>
                <th>Recipient</th>
                <th>Status</th>
                <th>Message</th>
                <th>Sent</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="text-sm">{{ $log->business?->name ?? '—' }}</td>
                    <td class="text-sm">{{ $log->channel }}</td>
                    <td class="text-sm text-slate-400">{{ $log->recipient }}</td>
                    <td>
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $log->status === 'sent' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' }}">{{ $log->status }}</span>
                    </td>
                    <td class="text-sm text-slate-400 max-w-md truncate">{{ $log->message }}</td>
                    <td class="text-sm text-slate-500">{{ $log->sent_at?->diffForHumans() ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-500 py-8">No notifications sent yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $logs->withQueryString()->links() }}</div>
@endsection
