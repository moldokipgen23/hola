@extends('layouts.admin')

@section('title', 'Directory Category Manager')
@section('header', 'Directory Category Manager')

@section('content')
<div class="max-w-6xl mx-auto">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-slate-400 text-sm">AI-assigned business classifications. These describe the <strong class="text-slate-300">type of business</strong> and are used by AI imports, search and discovery.</p>
            <p class="text-slate-500 text-xs mt-1">Shop product categories (Grocery, Food, Medicine...) are managed separately under Shop → Product Categories.</p>
        </div>
        <div class="flex gap-2">
            <button onclick="expandAll()" class="btn-ghost text-sm">Expand All</button>
            <button onclick="collapseAll()" class="btn-ghost text-sm">Collapse All</button>
            <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="btn-primary">Add Directory Category</button>
        </div>
    </div>

    <div class="space-y-2">
        @foreach($categories as $cat)
            @include('admin.categories._tree-item', ['category' => $cat, 'depth' => 0])
        @endforeach

        @if($categories->isEmpty())
            <div class="glass-card p-4 rounded-lg text-center">
                <p class="text-slate-500 text-sm">No directory classifications yet.</p>
            </div>
        @endif
    </div>
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center">
    <div class="glass-card p-6 rounded-xl w-full max-w-md mx-4">
        <h3 class="text-white font-semibold text-lg mb-4">Add Directory Category</h3>
        <form method="POST" action="{{ route('admin.category-tree.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Name</label>
                    <input type="text" name="name" required class="input-dark" placeholder="e.g. Restaurant, Cafe, Bakery">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Parent Classification (optional)</label>
                    <select name="parent_id" class="input-dark">
                        <option value="">None (root level)</option>
                        @foreach(\App\Models\Category::where('is_canonical', true)->root()->ordered()->get() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Classification Bucket</label>
                    <select name="module_type" class="input-dark">
                        <option value="directory">Directory</option>
                        <option value="ordering">Shop</option>
                        <option value="booking">Book</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Launch Phase</label>
                    <select name="launch_phase" class="input-dark">
                        <option value="phase1">Phase 1</option>
                        <option value="phase2">Phase 2</option>
                        <option value="phase3">Phase 3</option>
                    </select>
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded">
                        <span class="text-sm text-slate-300">Active</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="show_on_home" value="1" class="rounded">
                        <span class="text-sm text-slate-300">Show on homepage</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_featured" value="1" class="rounded">
                        <span class="text-sm text-slate-300">Featured</span>
                    </label>
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button type="submit" class="btn-primary">Create</button>
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function expandAll() {
    document.querySelectorAll('.tree-children').forEach(el => el.classList.remove('hidden'));
    document.querySelectorAll('.tree-toggle').forEach(el => el.innerHTML = '▾');
}
function collapseAll() {
    document.querySelectorAll('.tree-children').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tree-toggle').forEach(el => el.innerHTML = '▸');
}
function toggleTree(id) {
    const children = document.getElementById('children-' + id);
    const toggle = document.getElementById('toggle-' + id);
    if (children.classList.contains('hidden')) {
        children.classList.remove('hidden');
        toggle.innerHTML = '▾';
    } else {
        children.classList.add('hidden');
        toggle.innerHTML = '▸';
    }
}
</script>
@endsection
