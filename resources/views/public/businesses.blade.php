@extends('layouts.public')

@section('title', 'All Businesses | Hola - Churachandpur Directory')
@section('description', 'Browse all local businesses in Lamka, Churachandpur, Manipur, India')

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mb-2">All Businesses</h1>
        <p class="text-slate-500 text-sm">{{ $businesses->total() }} {{ Str::plural('business', $businesses->total()) }} in Churachandpur</p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-6">
    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="lg:col-span-2">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search businesses..."
                        class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 text-sm text-slate-900 focus:outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-100">
                </div>
            </div>
            <div>
                <select name="category" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 focus:outline-none focus:border-primary-300">
                    <option value="">All Categories</option>
                    @foreach(\App\Models\Category::active()->orderBy('name')->get() as $cat)
                        <option value="{{ $cat->slug }}" {{ request('category') == $cat->slug ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="area" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 focus:outline-none focus:border-primary-300">
                    <option value="">All Areas</option>
                    @foreach(\App\Models\Area::active()->where('slug', '!=', 'other')->withCount('businesses')->where('businesses_count', '>', 0)->orderBy('name')->get() as $area)
                        <option value="{{ $area->slug }}" {{ request('area') == $area->slug ? 'selected' : '' }}>{{ $area->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center justify-between mt-3">
            <div class="flex items-center gap-2">
                <select name="sort" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:border-primary-300">
                    <option value="latest" {{ request('sort') == 'latest' ? 'selected' : '' }}>Latest</option>
                    <option value="rating" {{ request('sort') == 'rating' ? 'selected' : '' }}>Top Rated</option>
                    <option value="name" {{ request('sort') == 'name' ? 'selected' : '' }}>A-Z</option>
                </select>
            </div>
            <button type="submit" class="px-5 py-2 rounded-lg bg-primary-500 text-white text-sm font-medium hover:bg-primary-600 transition-colors">Search</button>
        </div>
    </form>

    {{-- Results --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($businesses as $biz)
            <a href="/business/{{ $biz->slug }}" class="business-card bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-primary-200">
                {{-- Photo --}}
                <div class="h-44 bg-slate-100 relative overflow-hidden">
                    @if(!empty($biz->photos) && is_array($biz->photos) && count($biz->photos) > 0)
                        <img src="{{ str_starts_with($biz->photos[0], 'http') ? $biz->photos[0] : asset($biz->photos[0]) }}" alt="{{ $biz->name }}" class="w-full h-full object-cover" loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-50 to-accent-50">
                            <span class="text-4xl">📍</span>
                        </div>
                    @endif
                    @if($biz->claim_status === 'claimed')
                        <span class="absolute top-3 left-3 px-2 py-0.5 rounded-full bg-emerald-400 text-white text-xs font-semibold">Verified</span>
                    @endif
                </div>
                {{-- Info --}}
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <h3 class="text-sm font-semibold text-slate-900 truncate">{{ $biz->name }}</h3>
                        @if($biz->average_rating > 0)
                            <span class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold whitespace-nowrap">
                                ★ {{ number_format($biz->average_rating, 1) }}
                            </span>
                        @endif
                    </div>
                    @if($biz->category)
                        <span class="inline-block px-2 py-0.5 rounded bg-primary-50 text-primary-700 text-xs font-medium">{{ $biz->category->name }}</span>
                    @endif
                    <p class="text-xs text-slate-400 truncate mt-2">📍 {{ $biz->address ?: 'Churachandpur' }}</p>
                    @if($biz->phone)
                        <p class="text-xs text-slate-400 truncate mt-0.5">📞 {{ $biz->phone }}</p>
                    @endif
                </div>
            </a>
        @empty
            <div class="col-span-full text-center py-16">
                <p class="text-4xl mb-3">🔍</p>
                <p class="text-slate-500 text-lg font-medium">No businesses found</p>
                <p class="text-slate-400 text-sm mt-1">Try different search terms or filters</p>
                <a href="/businesses" class="inline-block mt-4 px-4 py-2 rounded-lg bg-primary-50 text-primary-600 text-sm font-medium hover:bg-primary-100">Clear filters</a>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-8">
        {{ $businesses->withQueryString()->links() }}
    </div>
</div>
@endsection
