@php
    $children = $category->children()->active()->ordered()->get();
    $hasChildren = $children->count() > 0;
    $businessCount = $category->businesses()->count();
@endphp

<div class="tree-item" style="padding-left: {{ $depth * 24 }}px">
    <div class="glass-card p-3 rounded-lg mb-2 flex items-center gap-3 hover:border-purple-500/30 transition-colors group">
        @if($hasChildren)
            <button onclick="toggleTree({{ $category->id }})" id="toggle-{{ $category->id }}" class="text-slate-400 hover:text-white text-sm w-4">▾</button>
        @else
            <span class="w-4"></span>
        @endif

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <span class="text-white font-medium text-sm">{{ $category->name }}</span>
                @if($category->is_featured)
                    <span class="badge badge-yellow text-xs">Featured</span>
                @endif
                @if(!$category->is_active)
                    <span class="badge badge-red text-xs">Inactive</span>
                @endif
                @if($category->show_on_home)
                    <span class="badge badge-blue text-xs">Home</span>
                @endif
                <span class="badge badge-green text-xs">{{ $category->launch_phase }}</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <span class="text-slate-500 text-xs">{{ $category->module_type }}</span>
                <span class="text-slate-500 text-xs">{{ $businessCount }} businesses</span>
                @if($hasChildren)
                    <span class="text-slate-500 text-xs">{{ $children->count() }} children</span>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <a href="{{ route('admin.categories.edit', $category->id) }}" class="text-slate-400 hover:text-white text-xs">Edit</a>
            <form method="POST" action="{{ route('admin.category-tree.toggle', $category->id) }}" class="inline">
                @csrf @method('PATCH')
                <button type="submit" class="text-slate-400 hover:text-white text-xs">{{ $category->is_active ? 'Deactivate' : 'Activate' }}</button>
            </form>
        </div>
    </div>

    @if($hasChildren)
    <div class="tree-children" id="children-{{ $category->id }}">
        @foreach($children as $child)
            @include('admin.categories._tree-item', ['category' => $child, 'depth' => $depth + 1])
        @endforeach
    </div>
    @endif
</div>
