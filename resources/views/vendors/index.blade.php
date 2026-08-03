@extends('layouts.admin')

@section('title', 'Vendors')
@section('header', 'Vendor Management')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Vendors</h3>
    <span class="text-slate-500 text-sm">{{ $vendors->total() }} vendors</span>
</div>

<!-- Filters -->
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search vendors..."
                class="input-dark w-full">
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="banned" {{ request('status') == 'banned' ? 'selected' : '' }}>Banned</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary w-full">Filter</button>
        </div>
    </div>
</form>

<!-- Table -->
<div class="glass-card rounded-xl overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Vendor</th>
                <th>Businesses</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vendors as $vendor)
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white text-sm font-bold">
                            {{ strtoupper(substr($vendor->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-white font-medium">{{ $vendor->name }}</p>
                            <p class="text-slate-500 text-xs">{{ $vendor->email ?? $vendor->phone }}</p>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge badge-blue">{{ $vendor->owned_businesses_count ?? 0 }}</span>
                </td>
                <td>
                    @if($vendor->banned_at)
                        <span class="badge badge-red">Banned</span>
                    @elseif($vendor->is_active)
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="badge badge-yellow">Inactive</span>
                    @endif
                </td>
                <td class="text-slate-400 text-xs">{{ $vendor->last_login_at ? $vendor->last_login_at->diffForHumans() : 'Never' }}</td>
                <td class="text-slate-400 text-xs">{{ $vendor->created_at->format('M d, Y') }}</td>
                <td>
                    <div class="flex gap-2">
                        <a href="{{ route('admin.vendors.show', $vendor->id) }}" class="px-3 py-1.5 text-xs rounded-lg bg-white/5 text-slate-300 hover:bg-white/10 transition">View</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center text-slate-500 py-12">No vendors found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $vendors->links() }}
</div>
@endsection
