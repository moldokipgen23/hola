@extends('layouts.admin')

@section('title', 'Users')
@section('header', 'User Management')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Users</h3>
    <a href="{{ route('admin.users.create') }}" class="btn-primary">+ Add User</a>
</div>

<!-- Filters -->
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search users..."
                class="input-dark w-full">
        </div>
        <div>
            <select name="role" class="input-dark w-full">
                <option value="">All Roles</option>
                <option value="customer" {{ request('role') == 'customer' ? 'selected' : '' }}>Customer</option>
                <option value="owner" {{ request('role') == 'owner' ? 'selected' : '' }}>Owner</option>
                <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="moderator" {{ request('role') == 'moderator' ? 'selected' : '' }}>Moderator</option>
                <option value="super_admin" {{ request('role') == 'super_admin' ? 'selected' : '' }}>Super Admin</option>
            </select>
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="banned" {{ request('status') == 'banned' ? 'selected' : '' }}>Banned</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div>
            <select name="verified" class="input-dark w-full">
                <option value="">All</option>
                <option value="1" {{ request('verified') == '1' ? 'selected' : '' }}>Verified</option>
                <option value="0" {{ request('verified') == '0' ? 'selected' : '' }}>Unverified</option>
            </select>
        </div>
    </div>
    <div class="flex gap-2 mt-3">
        <button type="submit" class="btn-primary px-6">Filter</button>
        <a href="{{ route('admin.users') }}" class="btn-ghost">Clear</a>
        <span class="text-slate-500 text-sm self-center ml-2">{{ $users->total() }} users</span>
    </div>
</form>

<!-- Bulk Actions -->
<div id="bulk-actions" class="glass-card p-3 rounded-xl mb-4 flex items-center gap-4" style="display:none">
    <span class="text-white text-sm"><span id="selected-count">0</span> selected</span>
    <button type="button" onclick="bulkAction('activate')" class="px-3 py-1.5 text-xs rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition">Activate</button>
    <button type="button" onclick="bulkAction('deactivate')" class="px-3 py-1.5 text-xs rounded-lg bg-yellow-500/10 text-yellow-400 hover:bg-yellow-500/20 transition">Deactivate</button>
    <button type="button" onclick="bulkAction('ban')" class="px-3 py-1.5 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition">Ban</button>
    <button type="button" onclick="bulkAction('unban')" class="px-3 py-1.5 text-xs rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition">Unban</button>
</div>

<form id="bulk-form" method="POST" action="{{ route('admin.users.bulk') }}">
    @csrf
    <input type="hidden" name="action" id="bulk-action-input">
    <input type="hidden" name="ids" id="bulk-ids-input">
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:40px"><input type="checkbox" id="select-all" onchange="toggleAll(this)"></th>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Verified</th>
                <th>Businesses</th>
                <th>Last Login</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                <tr>
                    <td><input type="checkbox" value="{{ $user->id }}" class="row-checkbox" onchange="updateBulk()"></td>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-white font-medium text-sm">{{ $user->name }}</p>
                                <p class="text-slate-500 text-xs">{{ $user->email ?? $user->phone ?? 'No contact' }}</p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="px-2 py-0.5 text-xs rounded-full
                            {{ $user->role === 'super_admin' ? 'bg-red-500/20 text-red-400' :
                               ($user->role === 'admin' ? 'bg-purple-500/20 text-purple-400' :
                               ($user->role === 'moderator' ? 'bg-blue-500/20 text-blue-400' :
                               ($user->role === 'owner' ? 'bg-green-500/20 text-green-400' :
                               'bg-slate-500/20 text-slate-400'))) }}">
                            {{ $user->role }}
                        </span>
                    </td>
                    <td>
                        @if($user->banned_at)
                            <span class="px-2 py-0.5 text-xs rounded-full bg-red-500/20 text-red-400">Banned</span>
                        @elseif(!$user->is_active)
                            <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-500/20 text-yellow-400">Inactive</span>
                        @else
                            <span class="px-2 py-0.5 text-xs rounded-full bg-green-500/20 text-green-400">Active</span>
                        @endif
                    </td>
                    <td>
                        @if($user->email_verified_at || $user->phone_verified_at)
                            <span class="text-green-400 text-xs">✓</span>
                        @else
                            <span class="text-slate-500 text-xs">—</span>
                        @endif
                    </td>
                    <td class="text-slate-400 text-sm">{{ $user->ownedBusinesses->count() }}</td>
                    <td class="text-slate-400 text-xs">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</td>
                    <td class="text-slate-400 text-xs">{{ $user->created_at->diffForHumans() }}</td>
                    <td>
                        <div class="flex gap-1">
                            <a href="{{ route('admin.users.show', $user->id) }}" class="px-2 py-1 text-xs rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20">View</a>
                            <a href="{{ route('admin.users.edit', $user->id) }}" class="px-2 py-1 text-xs rounded-lg bg-slate-500/10 text-slate-400 hover:bg-slate-500/20">Edit</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-slate-500 py-8">No users found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $users->withQueryString()->links() }}
</div>

<script>
function toggleAll(cb) {
    document.querySelectorAll('.row-checkbox').forEach(c => c.checked = cb.checked);
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

function bulkAction(action) {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm(action.charAt(0).toUpperCase() + action.slice(1) + ' ' + ids.length + ' users?')) return;
    document.getElementById('bulk-action-input').value = action;
    document.getElementById('bulk-ids-input').value = JSON.stringify(ids);
    document.getElementById('bulk-form').submit();
}
</script>
@endsection
