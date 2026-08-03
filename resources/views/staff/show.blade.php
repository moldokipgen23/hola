@extends('layouts.admin')

@section('title', $staff->name)
@section('header', 'Staff Details')

@section('content')
<div class="text-sm text-slate-500 mb-4">
    <a href="{{ route('admin.staff') }}" class="hover:text-white">Staff</a>
    <span class="mx-2">›</span>
    <span class="text-white">{{ $staff->name }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="glass-card p-6 rounded-xl">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xl font-bold">
                        {{ strtoupper(substr($staff->name, 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">{{ $staff->name }}</h2>
                        <p class="text-slate-400 text-sm">{{ $staff->email }}</p>
                    </div>
                </div>
                @if($staff->id !== Auth::id())
                    <a href="{{ route('admin.staff.edit', $staff->id) }}" class="btn-ghost">Edit</a>
                @endif
            </div>
            <div class="flex gap-3 mt-4">
                @if($staff->role === 'super_admin')
                    <span class="badge badge-red">Super Admin</span>
                @elseif($staff->role === 'admin')
                    <span class="badge badge-blue">Admin</span>
                @else
                    <span class="badge badge-green">Moderator</span>
                @endif
                @if($staff->is_active)
                    <span class="badge badge-green">Active</span>
                @else
                    <span class="badge badge-yellow">Inactive</span>
                @endif
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider text-slate-400">Staff Info</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Last Login</span>
                    <span class="text-white text-sm">{{ $staff->last_login_at ? $staff->last_login_at->diffForHumans() : 'Never' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Login Count</span>
                    <span class="text-white text-sm">{{ $staff->login_count }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Member Since</span>
                    <span class="text-white text-sm">{{ $staff->created_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
