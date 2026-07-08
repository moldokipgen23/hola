@extends('layouts.admin')

@section('title', 'Activity Logs')
@section('header', 'Activity Logs')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Activity Logs</h3>
    <span class="text-slate-500 text-sm">{{ $logs->total() }} events</span>
</div>

<!-- Filters -->
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div>
            <select name="action" class="input-dark w-full">
                <option value="">All Actions</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $action)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="user_id" class="input-dark w-full">
                <option value="">All Users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary w-full">Filter</button>
        </div>
        <div>
            <a href="{{ route('admin.activity-logs') }}" class="btn-ghost w-full text-center block">Clear</a>
        </div>
    </div>
</form>

<div class="glass-card rounded-xl overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Action</th>
                <th>Subject</th>
                <th>IP</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr>
                <td>
                    <span class="text-white text-sm">{{ $log->user?->name ?? 'System' }}</span>
                </td>
                <td>
                    <span class="badge badge-blue">{{ ucwords(str_replace('_', ' ', $log->action)) }}</span>
                </td>
                <td class="text-slate-400 text-sm">
                    @if($log->subject_type)
                        {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td class="text-slate-500 text-xs">{{ $log->ip_address }}</td>
                <td class="text-slate-500 text-xs">{{ $log->created_at->diffForHumans() }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center text-slate-500 py-12">No activity logs yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->withQueryString()->links() }}
</div>
@endsection
