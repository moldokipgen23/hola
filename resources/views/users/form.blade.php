@extends('layouts.admin')

@section('title', isset($user) ? 'Edit User' : 'Create User')
@section('header', isset($user) ? 'Edit User' : 'Create User')

@section('content')
<div class="flex justify-between items-center mb-6">
    <a href="{{ route('admin.users') }}" class="text-slate-400 hover:text-white">← Users</a>
</div>

<form method="POST" action="{{ isset($user) ? route('admin.users.update', $user->id) : route('admin.users.store') }}" class="max-w-2xl">
    @csrf
    @if(isset($user)) @method('PUT') @endif

    <div class="glass-card p-6 rounded-xl space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Name *</label>
            <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" class="input-dark w-full" required>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="input-dark w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone ?? '') }}" class="input-dark w-full">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">{{ isset($user) ? 'New Password (leave blank to keep)' : 'Password *' }}</label>
            <input type="password" name="password" class="input-dark w-full" {{ isset($user) ? '' : 'required' }}>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Role *</label>
                <select name="role" class="input-dark w-full" required>
                    <option value="customer" {{ old('role', $user->role ?? '') === 'customer' ? 'selected' : '' }}>Customer</option>
                    <option value="owner" {{ old('role', $user->role ?? '') === 'owner' ? 'selected' : '' }}>Owner</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Status</label>
                <select name="is_active" class="input-dark w-full">
                    <option value="1" {{ old('is_active', $user->is_active ?? 1) ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ old('is_active', $user->is_active ?? 1) ? '' : 'selected' }}>Inactive</option>
                </select>
            </div>
        </div>

        <div class="flex gap-2 pt-4">
            <button type="submit" class="btn-primary">{{ isset($user) ? 'Update User' : 'Create User' }}</button>
            <a href="{{ route('admin.users') }}" class="btn-ghost">Cancel</a>
        </div>
    </div>
</form>
@endsection
