@extends('layouts.admin')

@section('title', 'Claims')
@section('header', 'Claims')

@section('content')
<!-- Filters -->
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary px-6 w-full">Filter</button>
        </div>
        <div>
            <a href="{{ route('admin.claims') }}" class="btn-ghost w-full text-center">Clear</a>
        </div>
    </div>
</form>

<!-- Bulk Actions -->
<div id="bulk-actions" class="glass-card p-3 rounded-xl mb-4 flex items-center gap-4" style="display:none">
    <span class="text-white text-sm"><span id="selected-count">0</span> selected</span>
    <button type="button" onclick="bulkApprove()" class="px-3 py-1.5 text-xs rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition">Approve Selected</button>
    <button type="button" onclick="bulkReject()" class="px-3 py-1.5 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition">Reject Selected</button>
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
                <th>Message</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($claims ?? [] as $claim)
                <tr>
                    <td><input type="checkbox" value="{{ $claim->id }}" class="row-checkbox" onchange="updateBulk()"></td>
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
                <tr><td colspan="7" class="text-center text-slate-400">No claims.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($claims) && $claims->hasPages())
    <div class="mt-4 text-slate-400">{{ $claims->links() }}</div>
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

function bulkApprove() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Approve ' + ids.length + ' claims?')) return;
    document.getElementById('bulk-ids-input').value = JSON.stringify(ids);
    document.getElementById('bulk-form').action = '{{ route("admin.claims.bulk-approve") }}';
    document.getElementById('bulk-form').submit();
}

function bulkReject() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Reject ' + ids.length + ' claims?')) return;
    document.getElementById('bulk-ids-input').value = JSON.stringify(ids);
    document.getElementById('bulk-form').action = '{{ route("admin.claims.bulk-reject") }}';
    document.getElementById('bulk-form').submit();
}
</script>
@endsection
