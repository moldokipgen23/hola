@extends('vendor.layouts.dashboard')

@section('title', 'Media Library')
@section('header', 'Media Library')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div class="flex items-center gap-3">
        <select id="categoryFilter" onchange="filterByCategory(this.value)" class="input-dark text-sm">
            <option value="">All Categories</option>
            <option value="general">General</option>
            <option value="product">Product</option>
            <option value="banner">Banner</option>
            <option value="avatar">Avatar</option>
        </select>
    </div>
    <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="btn-primary">Upload File</button>
</div>

<!-- Media Grid -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4" id="mediaGrid">
    @forelse($media as $item)
        <div class="glass-card rounded-lg overflow-hidden group relative" data-category="{{ $item->category }}">
            <div class="aspect-square bg-dark-800 flex items-center justify-center">
                @if(str_starts_with($item->mime_type, 'image/'))
                    <img src="{{ asset('storage/' . $item->filename) }}" alt="{{ $item->alt_text ?? $item->original_filename }}" class="w-full h-full object-cover">
                @else
                    <div class="text-center p-4">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-8 h-8 text-slate-500 mx-auto mb-2"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-xs text-slate-500">{{ $item->mime_type }}</p>
                    </div>
                @endif
            </div>
            <div class="p-3">
                <p class="text-xs text-slate-300 truncate" title="{{ $item->original_filename }}">{{ $item->original_filename }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ number_format($item->size_bytes / 1024, 1) }} KB</p>
                <span class="badge badge-blue text-xs mt-1">{{ $item->category }}</span>
            </div>
            <form method="POST" action="{{ route('vendor.media.destroy', $item) }}" class="absolute top-2 right-2 hidden group-hover:block" data-confirm="Delete this media?">
                @csrf @method('DELETE')
                <button type="submit" class="bg-red-500/80 hover:bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs">&times;</button>
            </form>
        </div>
    @empty
        <div class="col-span-full text-center text-slate-400 py-12">No media files yet.</div>
    @endforelse
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="glass-card rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-white font-semibold">Upload File</h3>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        <form method="POST" action="{{ route('vendor.media.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">File</label>
                    <input type="file" name="file" class="input-dark" required accept="image/*,.pdf,.doc,.docx">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Category</label>
                    <select name="category" class="input-dark" required>
                        <option value="general">General</option>
                        <option value="product">Product</option>
                        <option value="banner">Banner</option>
                        <option value="avatar">Avatar</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Alt Text</label>
                    <input type="text" name="alt_text" class="input-dark" placeholder="Describe the image">
                </div>
            </div>
            <div class="flex items-center gap-3 mt-6">
                <button type="submit" class="btn-primary">Upload</button>
                <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" class="btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function filterByCategory(category) {
    document.querySelectorAll('#mediaGrid > div[data-category]').forEach(el => {
        if (!category || el.dataset.category === category) {
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
}
</script>
@endpush
