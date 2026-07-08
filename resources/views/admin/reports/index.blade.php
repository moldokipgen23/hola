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

<!-- Bulk Actions -->
<div id="bulk-actions" class="glass-card p-3 rounded-xl mb-4 flex items-center gap-4" style="display:none">
    <span class="text-white text-sm"><span id="selected-count">0</span> selected</span>
    <button type="button" onclick="bulkResolve()" class="px-3 py-1.5 text-xs rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition">Resolve Selected</button>
</div>

<form id="bulk-form" method="POST">
    @csrf
    <input type="hidden" name="ids" id="bulk-ids-input">
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:40px"><input type="checkbox" id="select-all" onchange="toggleAll(this)"></th>
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
                    <td><input type="checkbox" value="{{ $report->id }}" class="row-checkbox" onchange="updateBulk()"></td>
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
                <tr><td colspan="8" class="text-center text-slate-400">No reports.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($reports) && $reports->hasPages())
    <div class="mt-4 text-slate-400">{{ $reports->links() }}</div>
@endif

<script>
function toggleAll(el) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = el.checked);
    updateBulk();
}

function updateBulk() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    document.getElementById('selected-count').textContent = checked.length;
    document.getElementById('bulk-actions').style.display = checked.length > 0 ? 'flex' : 'none';
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
}

function bulkResolve() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Resolve ' + ids.length + ' reports?')) return;
    document.getElementById('bulk-ids-input').value = JSON.stringify(ids);
    document.getElementById('bulk-form').action = '{{ route("admin.reports.bulk-resolve") }}';
    document.getElementById('bulk-form').submit();
}
</script>
@endsection
