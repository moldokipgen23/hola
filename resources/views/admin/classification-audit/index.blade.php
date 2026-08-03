@extends('layouts.admin')

@section('title', 'Classification Audit')
@section('header', 'Classification Audit')

@section('content')
<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="stat-card">
        <div class="stat-icon bg-purple-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-purple-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Total Businesses</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['total'] ?? 0 }}</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-slate-500">
            Active businesses
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-green-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-green-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Classified</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['classified'] ?? 0 }}</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-green-500">
            {{ $stats['percentage'] ?? 0 }}% coverage
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-amber-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-amber-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Unclassified</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['unclassified'] ?? 0 }}</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-amber-500">
            Needs attention
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-red-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-red-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Inactive</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['inactive'] ?? 0 }}</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-red-500">
            Missing/missing active
        </div>
    </div>
</div>

<!-- Filters & Actions -->
<div class="glass-card p-4 rounded-xl mb-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div>
                <select name="filter" class="input-dark">
                    <option value="">All Businesses</option>
                    <option value="unclassified" {{ request('filter') === 'unclassified' ? 'selected' : '' }}>Unclassified Only</option>
                    <option value="inactive" {{ request('filter') === 'inactive' ? 'selected' : '' }}>Inactive Classifications</option>
                    <option value="classified" {{ request('filter') === 'classified' ? 'selected' : '' }}>Classified Only</option>
                </select>
            </div>
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search business name..." class="input-dark">
            </div>
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('admin.classification-audit') }}" class="btn-ghost">Clear</a>
        </form>

        <div class="flex items-center gap-3">
            <button onclick="fixUnclassified()" class="px-4 py-2 text-sm rounded-lg bg-purple-500/10 text-purple-400 hover:bg-purple-500/20 transition border border-purple-500/20">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4 inline-block mr-1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Auto-Fix Unclassified
            </button>
            <div id="fix-status" class="text-sm text-slate-400 hidden"></div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Business</th>
                <th>Category</th>
                <th>World</th>
                <th>Status</th>
                <th>Active</th>
            </tr>
        </thead>
        <tbody>
            @forelse($businesses ?? [] as $business)
                <tr>
                    <td class="text-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500/20 to-blue-500/20 flex items-center justify-center text-white text-xs font-bold">
                                {{ strtoupper(substr($business->business_name, 0, 2)) }}
                            </div>
                            <div>
                                <p class="text-white font-medium">{{ $business->business_name }}</p>
                                <p class="text-slate-500 text-xs">ID: {{ $business->id }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm text-slate-300">{{ $business->category_name ?? '-' }}</td>
                    <td class="text-sm text-slate-300">{{ $business->world_name ?? '-' }}</td>
                    <td>
                        @if(is_null($business->classification_id))
                            <span class="badge badge-red">Missing</span>
                        @elseif($business->classification_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-yellow">Inactive</span>
                        @endif
                    </td>
                    <td>
                        @if($business->business_active)
                            <span class="badge badge-green">Yes</span>
                        @else
                            <span class="badge badge-red">No</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-slate-400 py-8">
                        No businesses found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($businesses) && $businesses->hasPages())
    <div class="mt-4 text-slate-400">{{ $businesses->links() }}</div>
@endif

<script>
function fixUnclassified() {
    const status = document.getElementById('fix-status');
    status.classList.remove('hidden');
    status.textContent = 'Fixing unclassified businesses...';
    status.className = 'text-sm text-amber-400';

    fetch('{{ route("admin.classification-audit.fix") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        status.textContent = data.message;
        status.className = 'text-sm text-green-400';
        setTimeout(() => location.reload(), 1500);
    })
    .catch(error => {
        status.textContent = 'Failed to fix. Please try again.';
        status.className = 'text-sm text-red-400';
    });
}
</script>
@endsection
