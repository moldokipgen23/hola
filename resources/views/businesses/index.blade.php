@extends('layouts.admin')

@section('title', 'Businesses')
@section('header', 'Businesses')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Businesses</h3>
    <div class="flex gap-2">
        <button onclick="detectChanges()" class="px-4 py-2 text-sm rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition">Detect Changes</button>
        <a href="{{ route('admin.businesses.create') }}" class="btn-primary">+ Add Business</a>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search businesses..."
                class="input-dark w-full">
        </div>
        <div>
            <select name="category" class="input-dark w-full">
                <option value="">All Categories</option>
                @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
                    <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div>
            <select name="featured" class="input-dark w-full">
                <option value="">All</option>
                <option value="1" {{ request('featured') == '1' ? 'selected' : '' }}>Featured</option>
                <option value="0" {{ request('featured') == '0' ? 'selected' : '' }}>Not Featured</option>
            </select>
        </div>
    </div>
    <div class="flex gap-2 mt-3">
        <button type="submit" class="btn-primary px-6">Filter</button>
        <a href="{{ route('admin.businesses') }}" class="btn-ghost">Clear</a>
        <span class="text-slate-500 text-sm self-center ml-2">{{ $businesses->total() }} results</span>
    </div>
</form>

<!-- Bulk Actions -->
<div id="bulk-actions" class="glass-card p-3 rounded-xl mb-4 flex items-center gap-4" style="display:none">
    <span class="text-white text-sm"><span id="selected-count">0</span> selected</span>
    <button type="button" onclick="bulkAction('activate')" class="px-3 py-1.5 text-xs rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition">Activate</button>
    <button type="button" onclick="bulkAction('deactivate')" class="px-3 py-1.5 text-xs rounded-lg bg-yellow-500/10 text-yellow-400 hover:bg-yellow-500/20 transition">Deactivate</button>
    <button type="button" onclick="bulkAction('feature')" class="px-3 py-1.5 text-xs rounded-lg bg-purple-500/10 text-purple-400 hover:bg-purple-500/20 transition">Feature</button>
    <button type="button" onclick="bulkAction('unfeature')" class="px-3 py-1.5 text-xs rounded-lg bg-slate-500/10 text-slate-400 hover:bg-slate-500/20 transition">Unfeature</button>
    <button type="button" onclick="bulkDelete()" class="px-3 py-1.5 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition">Delete Selected</button>
</div>

<form id="bulk-form" method="POST" action="{{ route('admin.businesses.bulk') }}">
    @csrf
    <input type="hidden" name="action" id="bulk-action-input">
    <input type="hidden" name="ids" id="bulk-ids-input">
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:40px"><input type="checkbox" id="select-all" onchange="toggleAll(this)"></th>
                <th>Name</th>
                <th>Category</th>
                <th>Status</th>
                <th>Featured</th>
                <th>Views</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($businesses ?? [] as $business)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $business->id }}" class="row-checkbox" onchange="updateBulk()"></td>
                    <td class="font-medium">
                        <div>{{ $business->name }}</div>
                        <div class="text-xs text-slate-500">{{ $business->address }}</div>
                    </td>
                    <td class="text-sm">{{ $business->category->name ?? '-' }}</td>
                    <td>
                        @if($business->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td>
                        @if($business->is_featured)
                            <span class="badge badge-yellow">Featured</span>
                        @else
                            <span class="text-xs text-slate-500">-</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $business->views_count }}</td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('admin.businesses.show', $business->id) }}" class="text-emerald-400 hover:text-emerald-300">View</a>
                        <a href="{{ route('admin.businesses.edit', $business->id) }}" class="text-blue-400 hover:text-blue-300">Edit</a>
                        <form method="POST" action="{{ route('admin.businesses.destroy', $business->id) }}" data-confirm="Delete this business?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400">No businesses match your filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($businesses ?? collect(), 'links'))
    <div class="mt-4 text-slate-400">
        {{ $businesses->links() }}
    </div>
@endif

<script>
function toggleAll(el) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = el.checked);
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
    document.getElementById('bulk-action-input').value = action;
    document.getElementById('bulk-ids-input').value = JSON.stringify(ids);
    document.getElementById('bulk-form').submit();
}

function bulkDelete() {
    if (!confirm('Delete selected businesses? This cannot be undone.')) return;
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    document.getElementById('bulk-action-input').value = 'delete';
    document.getElementById('bulk-ids-input').value = JSON.stringify(ids);
    document.getElementById('bulk-form').submit();
}

async function detectChanges() {
    if (!confirm('Check all imported businesses for updates from Google? This may take a minute.')) return;
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Checking...';
    
    try {
        const response = await fetch('{{ route("admin.businesses.detect-changes") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            },
        });
        
        const data = await response.json();
        alert(data.message || 'Change detection completed');
        if (data.output) console.log(data.output);
        location.reload();
    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Detect Changes';
    }
}
</script>
@endsection
