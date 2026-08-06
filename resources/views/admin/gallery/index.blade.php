@extends('layouts.admin')

@section('title', 'Business Photo Gallery')
@section('header', 'Business Photo Gallery')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <h3 class="text-white font-semibold text-lg">Business Photos</h3>
        <p class="text-slate-500 text-sm mt-1">Caption, order, set cover and moderate gallery photos.</p>
    </div>
</div>

<div class="flex gap-1 mb-4 border-b border-white/10 overflow-x-auto">
    @foreach(['all' => 'All', 'approved' => 'Approved', 'pending' => 'Pending', 'hidden' => 'Hidden'] as $key => $label)
        <a href="{{ route('admin.gallery', ['status' => $key === 'all' ? null : $key]) }}"
            class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ ($status ?? 'all') === $key ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
            {{ $label }} ({{ $counts[$key] ?? 0 }})
        </a>
    @endforeach
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <select name="business_id" class="input-dark w-full">
                <option value="">All Businesses</option>
                @foreach($businesses as $biz)
                    <option value="{{ $biz->id }}" {{ request('business_id') == $biz->id ? 'selected' : '' }}>{{ $biz->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search filename, caption or business…" class="input-dark w-full">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('admin.gallery') }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
@forelse($media as $item)
    <div class="glass-card rounded-xl overflow-hidden">
        <div class="relative aspect-video bg-slate-800">
            <img src="{{ asset('storage/'.$item->filename) }}" alt="{{ $item->alt_text ?? $item->original_filename }}"
                class="w-full h-full object-cover">
            @if($item->is_cover)
                <span class="absolute top-2 left-2 px-2 py-0.5 text-xs rounded-full bg-emerald-500/80 text-white font-medium">Cover</span>
            @endif
            <span class="absolute top-2 right-2 px-2 py-0.5 text-xs rounded-full
                {{ $item->status === 'approved' ? 'bg-green-500/80' : ($item->status === 'pending' ? 'bg-yellow-500/80' : 'bg-red-500/80') }} text-white font-medium">
                {{ ucfirst($item->status) }}
            </span>
        </div>
        <div class="p-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm text-white font-medium truncate">{{ $item->original_filename }}</p>
                <span class="text-xs text-slate-500 shrink-0">{{ $item->business?->name }}</span>
            </div>
            <p class="text-xs text-slate-500 mt-1">{{ $item->category }} · {{ number_format($item->size_bytes / 1024, 1) }} KB</p>
            @if($item->moderation_reason)
                <p class="text-red-400 text-xs mt-1">{{ $item->moderation_reason }}</p>
            @endif

            <form method="POST" action="{{ route('admin.gallery.detail', $item->id) }}" class="mt-3 space-y-2">
                @csrf @method('PUT')
                <input type="text" name="alt_text" value="{{ $item->alt_text }}" placeholder="Caption" class="input-dark w-full text-sm">
                <div class="flex gap-2 items-center">
                    <input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" max="9999" class="input-dark w-20 text-sm" title="Sort order">
                    <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                        <input type="checkbox" name="is_cover" value="1" {{ $item->is_cover ? 'checked' : '' }} class="accent-emerald-500">
                        Cover
                    </label>
                    <button class="ml-auto px-3 py-1.5 rounded-lg bg-sky-500/10 text-sky-400 text-xs">Save</button>
                </div>
            </form>

            <div class="flex flex-wrap gap-2 mt-3">
                @foreach(['approved', 'pending', 'hidden'] as $st)
                    @if($item->status !== $st)
                        <form method="POST" action="{{ route('admin.gallery.moderate', $item->id) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="{{ $st }}">
                            <button class="px-2 py-1 rounded-lg text-xs {{ $st === 'approved' ? 'bg-green-500/10 text-green-400' : ($st === 'pending' ? 'bg-yellow-500/10 text-yellow-400' : 'bg-red-500/10 text-red-400') }}">
                                {{ ucfirst($st) }}
                            </button>
                        </form>
                    @endif
                @endforeach
                <form method="POST" action="{{ route('admin.gallery.destroy', $item->id) }}" data-confirm="Delete this photo?" class="inline">
                    @csrf @method('DELETE')
                    <button class="px-2 py-1 rounded-lg text-xs bg-red-500/10 text-red-400">Delete</button>
                </form>
            </div>
        </div>
    </div>
@empty
    <div class="col-span-full glass-card p-10 rounded-xl text-center text-slate-500">No photos found.</div>
@endforelse
</div>

<div class="mt-6">{{ $media->links() }}</div>
@endsection