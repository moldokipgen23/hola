@extends('vendor.layouts.dashboard')

@section('title', 'Settings')
@section('header', 'Settings')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('vendor.settings.update') }}" class="glass-card p-6 rounded-lg space-y-4">
        @csrf @method('PUT')
        <h3 class="text-white font-semibold text-lg mb-4">Profile</h3>

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="input-dark">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Email</label>
            <input type="email" value="{{ $user->email }}" disabled class="input-dark opacity-60">
            <p class="text-xs text-slate-600 mt-1">Email cannot be changed.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="input-dark">
        </div>
        <button type="submit" class="btn-primary">Save Changes</button>
    </form>

    <form method="POST" action="{{ route('vendor.settings.password') }}" class="glass-card p-6 rounded-lg space-y-4">
        @csrf @method('PUT')
        <h3 class="text-white font-semibold text-lg mb-4">Change Password</h3>

        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Current Password</label>
            <input type="password" name="current_password" required class="input-dark">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">New Password</label>
            <input type="password" name="password" required class="input-dark">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Confirm New Password</label>
            <input type="password" name="password_confirmation" required class="input-dark">
        </div>
        <button type="submit" class="btn-primary">Change Password</button>
    </form>
</div>
@endsection
