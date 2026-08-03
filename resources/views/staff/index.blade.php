@extends('layouts.admin')

@section('title', 'Staff')
@section('header', 'Staff Management')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Admin Staff</h3>
    <a href="{{ route('admin.staff.create') }}" class="btn-primary">+ Add Staff</a>
</div>

<div class="glass-card rounded-xl overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Staff</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($staff as $member)
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold">
                            {{ strtoupper(substr($member->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-white font-medium">{{ $member->name }}</p>
                            <p class="text-slate-500 text-xs">{{ $member->email }}</p>
                        </div>
                    </div>
                </td>
                <td>
                    @if($member->role === 'super_admin')
                        <span class="badge badge-red">Super Admin</span>
                    @elseif($member->role === 'admin')
                        <span class="badge badge-blue">Admin</span>
                    @elseif($member->role === 'moderator')
                        <span class="badge badge-green">Moderator</span>
                    @endif
                </td>
                <td>
                    @if($member->is_active)
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="badge badge-yellow">Inactive</span>
                    @endif
                </td>
                <td class="text-slate-400 text-xs">{{ $member->last_login_at ? $member->last_login_at->diffForHumans() : 'Never' }}</td>
                <td class="text-slate-400 text-xs">{{ $member->created_at->format('M d, Y') }}</td>
                <td>
                    <div class="flex gap-2">
                        <a href="{{ route('admin.staff.show', $member->id) }}" class="px-3 py-1.5 text-xs rounded-lg bg-white/5 text-slate-300 hover:bg-white/10 transition">View</a>
                        <a href="{{ route('admin.staff.edit', $member->id) }}" class="px-3 py-1.5 text-xs rounded-lg bg-white/5 text-slate-300 hover:bg-white/10 transition">Edit</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center text-slate-500 py-12">No staff members found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $staff->links() }}
</div>
@endsection
