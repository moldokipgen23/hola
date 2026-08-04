@extends('layouts.admin')

@section('title', 'Business Owners')
@section('header', 'Business Owners')

@section('content')
@php
    $typeTabs = ['shopping' => 'Shopping', 'booking' => 'Booking', 'taxi' => 'Taxi'];
    $filterQuery = request()->except(['type', 'page']);
@endphp

<div class="flex justify-between items-center mb-4">
    <div>
        <h3 class="text-white font-semibold text-lg">All Business Owners</h3>
        <p class="text-slate-500 text-sm mt-1">{{ $vendors->total() }} owned businesses</p>
    </div>
    <a href="{{ route('admin.businesses.create') }}" class="btn-primary">Add Business</a>
</div>

<!-- Business type tabs -->
<div class="flex gap-1 mb-4 border-b border-white/10 overflow-x-auto">
    <a href="{{ route('admin.vendors', $filterQuery) }}"
        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ ! request('type') ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
        All
    </a>
    @foreach($typeTabs as $key => $label)
        <a href="{{ route('admin.vendors', array_merge($filterQuery, ['type' => $key])) }}"
            class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ request('type') == $key ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<!-- Filters -->
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search owner or business..."
                class="input-dark w-full">
        </div>
        <div>
            <select name="category_id" class="input-dark w-full">
                <option value="">All Sub-Types</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="verification_status" class="input-dark w-full">
                <option value="">All Verification</option>
                <option value="pending" {{ request('verification_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="verified" {{ request('verification_status') == 'verified' ? 'selected' : '' }}>Verified</option>
                <option value="rejected" {{ request('verification_status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </div>
        <div>
            <select name="owner_status" class="input-dark w-full">
                <option value="">Owner Status</option>
                <option value="active" {{ request('owner_status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="banned" {{ request('owner_status') == 'banned' ? 'selected' : '' }}>Banned</option>
            </select>
        </div>
        <div class="flex gap-2 col-span-2 md:col-span-4 lg:col-span-6">
            <button type="submit" class="btn-primary w-auto">Filter</button>
            <a href="{{ route('admin.vendors') }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<!-- Table -->
<div class="glass-card rounded-xl overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Owner</th>
                <th>Business</th>
                <th>Type</th>
                <th>Sub-Type</th>
                <th>Verification</th>
                <th>Owner Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vendors as $business)
            <tr>
                <td>
                    @if($business->createdBy)
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white text-sm font-bold">
                                {{ strtoupper(substr($business->createdBy->name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <a href="{{ route('admin.vendors.show', $business->created_by) }}" class="text-white font-medium hover:text-blue-400 transition">
                                    {{ $business->createdBy->name }}
                                </a>
                                <p class="text-slate-500 text-xs">{{ $business->createdBy->email ?? $business->createdBy->phone }}</p>
                            </div>
                        </div>
                    @else
                        <span class="text-slate-500 text-sm">No owner</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.businesses.show', $business->id) }}" class="text-white font-medium hover:text-blue-400 transition">
                        {{ $business->name }}
                    </a>
                    <p class="text-slate-500 text-xs mt-0.5">{{ $business->address }}</p>
                </td>
                <td>
                    @if($business->hasModule('transport'))
                        <span class="badge badge-yellow">Taxi / Transport</span>
                    @elseif($business->hasModule('bookings'))
                        <span class="badge badge-blue">Booking</span>
                    @elseif($business->hasModule('orders') || $business->hasModule('catalog'))
                        <span class="badge badge-green">Shopping</span>
                    @else
                        <span class="badge bg-slate-500/20 text-slate-400">Directory Only</span>
                    @endif
                </td>
                <td class="text-sm">{{ $business->category->name ?? '-' }}</td>
                <td>
                    @if($business->verification_status === 'verified')
                        <span class="badge badge-green">Verified</span>
                    @elseif($business->verification_status === 'rejected')
                        <span class="badge badge-red">Rejected</span>
                    @else
                        <span class="badge badge-yellow">Pending</span>
                    @endif
                </td>
                <td>
                    @if($business->createdBy && $business->createdBy->banned_at)
                        <span class="badge badge-red">Banned</span>
                    @elseif($business->createdBy)
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="text-slate-500 text-sm">—</span>
                    @endif
                </td>
                <td>
                    <div class="flex gap-2 flex-wrap">
                        <a href="{{ route('admin.businesses.show', $business->id) }}" class="px-3 py-1.5 text-xs rounded-lg bg-white/5 text-slate-300 hover:bg-white/10 transition">View</a>
                        <a href="{{ route('admin.businesses.edit', $business->id) }}" class="px-3 py-1.5 text-xs rounded-lg bg-white/5 text-slate-300 hover:bg-white/10 transition">Edit</a>
                        @if($business->verification_status !== 'verified')
                            <form method="POST" action="{{ route('admin.businesses.verify', $business->id) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 transition">Verify</button>
                            </form>
                        @endif
                        @if($business->createdBy && ! $business->createdBy->banned_at)
                            <form method="POST" action="{{ route('admin.users.ban', $business->created_by) }}" data-confirm="Suspend this owner?">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition">Suspend</button>
                            </form>
                        @elseif($business->createdBy && $business->createdBy->banned_at)
                            <form method="POST" action="{{ route('admin.users.unban', $business->created_by) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition">Unsuspend</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-slate-500 py-12">No business owners found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $vendors->links() }}
</div>
@endsection
