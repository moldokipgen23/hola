@extends('layouts.admin')

@section('title', $user->name)
@section('header', 'User Detail')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.users') }}" class="text-slate-400 hover:text-white">← Users</a>
        <h3 class="text-white font-semibold text-lg">{{ $user->name }}</h3>
        @if($user->banned_at)
            <span class="px-2 py-0.5 text-xs rounded-full bg-red-500/20 text-red-400">Banned</span>
        @elseif(!$user->is_active)
            <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-500/20 text-yellow-400">Inactive</span>
        @else
            <span class="px-2 py-0.5 text-xs rounded-full bg-green-500/20 text-green-400">Active</span>
        @endif
        <span class="px-2 py-0.5 text-xs rounded-full
            {{ $user->role === 'super_admin' ? 'bg-red-500/20 text-red-400' :
               ($user->role === 'admin' ? 'bg-purple-500/20 text-purple-400' :
               ($user->role === 'moderator' ? 'bg-blue-500/20 text-blue-400' :
               ($user->role === 'owner' ? 'bg-green-500/20 text-green-400' :
               'bg-slate-500/20 text-slate-400'))) }}">
            {{ $user->role }}
        </span>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn-primary text-sm">Edit User</a>
        @if($user->banned_at)
            <form method="POST" action="{{ route('admin.users.unban', $user->id) }}">
                @csrf
                <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20" onclick="return confirm('Unban this user?')">Unban</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.users.ban', $user->id) }}">
                @csrf
                <input type="text" name="reason" placeholder="Ban reason (optional)" class="input-dark text-sm mr-2" style="width:200px;display:inline-block">
                <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20" onclick="return confirm('Ban this user?')">Ban</button>
            </form>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- User Info -->
    <div class="glass-card p-6">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-2xl font-bold">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div>
                <h3 class="text-white font-semibold text-lg">{{ $user->name }}</h3>
                <p class="text-slate-400 text-sm">{{ $user->email ?? 'No email' }}</p>
                <p class="text-slate-400 text-sm">{{ $user->phone ?? 'No phone' }}</p>
            </div>
        </div>

        <div class="space-y-3">
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Role</p>
                <p class="text-white font-medium">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</p>
            </div>
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Email Verified</p>
                <p class="text-white font-medium">{{ $user->email_verified_at ? 'Yes (' . $user->email_verified_at->diffForHumans() . ')' : 'No' }}</p>
            </div>
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Phone Verified</p>
                <p class="text-white font-medium">{{ $user->phone_verified_at ? 'Yes' : 'No' }}</p>
            </div>
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Last Login</p>
                <p class="text-white font-medium">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</p>
            </div>
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Login Count</p>
                <p class="text-white font-medium">{{ $user->login_count }}</p>
            </div>
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Joined</p>
                <p class="text-white font-medium">{{ $user->created_at->format('M d, Y') }}</p>
            </div>
            @if($user->banned_at)
                <div class="bg-red-500/10 rounded-xl p-3">
                    <p class="text-xs text-red-400 mb-1">Banned</p>
                    <p class="text-red-300 font-medium">{{ $user->banned_at->diffForHumans() }}</p>
                    @if($user->ban_reason)
                        <p class="text-red-400 text-xs mt-1">Reason: {{ $user->ban_reason }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Owned Businesses -->
    <div class="glass-card p-6">
        <h3 class="text-white font-semibold mb-4">Owned Businesses ({{ $user->ownedBusinesses->count() }})</h3>
        @if($user->ownedBusinesses->count())
            <div class="space-y-2">
                @foreach($user->ownedBusinesses as $business)
                    <a href="{{ route('admin.businesses.show', $business->id) }}" class="block bg-white/5 rounded-xl p-3 hover:bg-white/10 transition">
                        <p class="text-white text-sm font-medium">{{ $business->name }}</p>
                        <p class="text-slate-500 text-xs">{{ $business->address }}</p>
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-slate-500 text-sm">No businesses owned.</p>
        @endif
    </div>

    <!-- Activity -->
    <div class="glass-card p-6">
        <h3 class="text-white font-semibold mb-4">Activity</h3>
        <div class="space-y-3">
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Reviews</p>
                <p class="text-white font-medium">{{ $user->reviews->count() }}</p>
            </div>
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Saved Listings</p>
                <p class="text-white font-medium">{{ $user->savedListings->count() }}</p>
            </div>
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Reports</p>
                <p class="text-white font-medium">{{ $user->reports->count() }}</p>
            </div>
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Claims</p>
                <p class="text-white font-medium">{{ $user->claimRequests->count() }}</p>
            </div>
            <div class="bg-white/5 rounded-xl p-3">
                <p class="text-xs text-slate-500 mb-1">Conversations</p>
                <p class="text-white font-medium">{{ $user->conversations->count() }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
