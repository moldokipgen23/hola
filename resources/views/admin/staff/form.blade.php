@extends('layouts.admin')

@section('title', isset($staff) ? 'Edit Staff' : 'Add Staff')
@section('header', isset($staff) ? 'Edit Staff Member' : 'Add Staff Member')

@php $isEdit = isset($staff) && $staff->id; @endphp

@section('content')
<div class="text-sm text-slate-500 mb-4">
    <a href="{{ route('admin.staff') }}" class="hover:text-white">Staff</a>
    <span class="mx-2">›</span>
    <span class="text-white">{{ $isEdit ? $staff->name : 'New' }}</span>
</div>

<div class="max-w-2xl">
    <form method="POST" action="{{ $isEdit ? route('admin.staff.update', $staff->id) : route('admin.staff.store') }}" class="glass-card p-6 rounded-xl space-y-4">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div>
            <label class="block text-slate-400 text-sm mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name', $isEdit ? $staff->name : '') }}" class="input-dark" required>
        </div>

        <div>
            <label class="block text-slate-400 text-sm mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $isEdit ? $staff->email : '') }}" class="input-dark" required>
        </div>

        <div>
            <label class="block text-slate-400 text-sm mb-1">Password {{ $isEdit ? '(leave blank to keep current)' : '' }}</label>
            <input type="password" name="password" class="input-dark" {{ $isEdit ? '' : 'required' }} minlength="6">
        </div>

        <div>
            <label class="block text-slate-400 text-sm mb-1">Role</label>
            <select name="role" class="input-dark" required>
                <option value="moderator" {{ (old('role', $isEdit ? $staff->role : '') == 'moderator') ? 'selected' : '' }}>Moderator</option>
                <option value="admin" {{ (old('role', $isEdit ? $staff->role : '') == 'admin') ? 'selected' : '' }}>Admin</option>
                <option value="super_admin" {{ (old('role', $isEdit ? $staff->role : '') == 'super_admin') ? 'selected' : '' }}>Super Admin</option>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" id="is_active" class="rounded" {{ old('is_active', $isEdit ? $staff->is_active : true) ? 'checked' : '' }}>
            <label for="is_active" class="text-slate-400 text-sm">Active</label>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">{{ $isEdit ? 'Update' : 'Create' }} Staff</button>
            <a href="{{ route('admin.staff') }}" class="btn-ghost">Cancel</a>
            @if($isEdit && $staff->id !== Auth::id())
                <button type="button" onclick="if(confirm('Delete this staff member?')){ document.getElementById('delete-form').submit(); }" class="btn-danger ml-auto">Delete</button>
            @endif
        </div>
    </form>

    @if($isEdit && $staff->id !== Auth::id())
        <form id="delete-form" method="POST" action="{{ route('admin.staff.destroy', $staff->id) }}" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endif
</div>
@endsection
