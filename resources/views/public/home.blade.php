@extends('layouts.public')

@section('title', 'Hola - Discover Local Businesses in Churachandpur')
@section('description', 'Find the best restaurants, shops, services, and businesses in Lamka, Churachandpur, Manipur. Browse by category, area, or search directly.')

@section('content')
{{-- Hero Section --}}
<section class="hero-gradient py-10 md:py-16">
    <div class="max-w-6xl mx-auto px-4 text-center">
        <h1 class="text-3xl md:text-5xl font-extrabold text-slate-900 mb-3 leading-tight">
            Discover Local Businesses<br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-500 to-accent-500">in Churachandpur</span>
        </h1>
        <p class="text-slate-500 text-base md:text-lg mb-8 max-w-xl mx-auto">
            Find restaurants, shops, services, and businesses in Lamka and surrounding areas.
        </p>

        {{-- Search Bar --}}
        <div class="max-w-2xl mx-auto relative" id="searchContainer">
            <div class="relative">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="searchInput" placeholder="Search restaurants, shops, services..."
                    class="w-full pl-12 pr-4 py-4 rounded-2xl border border-slate-200 bg-white text-slate-900 text-base shadow-lg shadow-slate-200/50 focus:outline-none search-glow transition-shadow" autocomplete="off">
                <div id="searchSpinner" class="hidden absolute right-4 top-1/2 -translate-y-1/2">
                    <svg class="animate-spin h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                </div>
            </div>
            <div id="searchResults" class="hidden absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl border border-slate-200 shadow-xl shadow-slate-200/50 search-dropdown z-50"></div>
        </div>

        {{-- Popular Areas --}}
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <span class="text-sm text-slate-400 mr-1">Popular:</span>
            @php
                $popularAreas = \App\Models\Area::active()->where('slug', '!=', 'other')->withCount('businesses')->orderByDesc('businesses_count')->limit(4)->get()->filter(fn($a) => $a->businesses_count > 0)->take(4);
            @endphp
            @foreach($popularAreas as $area)
                <a href="/area/{{ $area->slug }}" class="px-3 py-1.5 rounded-full bg-white border border-slate-200 text-sm text-slate-600 hover:border-primary-300 hover:text-primary-600 hover:bg-primary-50 transition-colors">
                    {{ $area->name }} <span class="text-slate-400 text-xs">({{ $area->businesses_count }})</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Category Icons (UC-style horizontal scroll) --}}
<section class="max-w-6xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Browse by Category</h2>
            <p class="text-sm text-slate-500 mt-1">Find what you're looking for</p>
        </div>
        <a href="/categories" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all →</a>
    </div>
    @php
        $categories = \App\Models\Category::active()->withCount('businesses')->orderByDesc('businesses_count')->limit(8)->get();
        $categoryIcons = [
            'food' => '🍽️', 'restaurant' => '🍽️', 'healthcare' => '🏥', 'hospital' => '🏥',
            'beauty' => '💇', 'salon' => '💇', 'shopping' => '🛍️', 'store' => '🛍️',
            'hotel' => '🏨', 'lodge' => '🏨', 'electronics' => '📱', 'mobile' => '📱',
            'bank' => '🏦', 'finance' => '🏦', 'education' => '🏫', 'school' => '🏫',
            'automotive' => '🚗', 'garage' => '🚗', 'professional' => '💼', 'office' => '💼',
            'church' => '⛪', 'worship' => '⛪', 'preschool' => '🧒', 'nursery' => '🧒',
            'establishment' => '📍', 'general' => '📦', 'sports' => '⚽', 'gym' => '💪',
        ];
    @endphp
    <div class="flex gap-3 overflow-x-auto pb-2 -mx-4 px-4 scrollbar-hide">
        @foreach($categories as $cat)
            @php
                $icon = '📍';
                foreach($categoryIcons as $key => $emoji) {
                    if(stripos($cat->name, $key) !== false) { $icon = $emoji; break; }
                }
            @endphp
            <a href="/category/{{ $cat->slug }}" class="flex-shrink-0 w-24 text-center group">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-primary-50 flex items-center justify-center text-3xl group-hover:bg-primary-100 transition-colors">
                    {{ $icon }}
                </div>
                <p class="text-xs font-medium text-slate-700 mt-2 group-hover:text-primary-600 transition-colors">{{ $cat->name }}</p>
                <p class="text-xs text-slate-400">{{ $cat->businesses_count }}</p>
            </a>
        @endforeach
    </div>
</section>

{{-- Top Rated (UC-style horizontal scroll) --}}
@php
    $topRated = \App\Models\Business::active()->with('category', 'area')
        ->where('average_rating', '>', 0)
        ->orderByDesc('average_rating')
        ->orderByDesc('review_count')
        ->limit(8)
        ->get();
@endphp
@if($topRated->count())
<section class="bg-white border-y border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Top Rated</h2>
                <p class="text-sm text-slate-500 mt-1">Best businesses in Churachandpur</p>
            </div>
            <a href="/businesses?sort=rating" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all →</a>
        </div>
        <div class="flex gap-4 overflow-x-auto pb-2 -mx-4 px-4 scrollbar-hide">
            @foreach($topRated as $biz)
                @include('partials._business-card', ['business' => $biz, 'variant' => 'compact'])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Explore by Area --}}
<section class="max-w-6xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Explore by Area</h2>
            <p class="text-sm text-slate-500 mt-1">Browse businesses in your neighborhood</p>
        </div>
        <a href="/areas" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all →</a>
    </div>
    @php
        $areasList = \App\Models\Area::active()->where('slug', '!=', 'other')->withCount('businesses')->orderByDesc('businesses_count')->limit(6)->get()->filter(fn($a) => $a->businesses_count > 0)->take(6);
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        @foreach($areasList as $area)
            <a href="/area/{{ $area->slug }}" class="area-card bg-gradient-to-br from-slate-50 to-white rounded-xl border border-slate-100 p-5 hover:border-primary-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary-50 flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ $area->name }}</p>
                        <p class="text-xs text-slate-400">{{ $area->businesses_count }} {{ Str::plural('business', $area->businesses_count) }}</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</section>

{{-- Featured Businesses --}}
@php
    $featured = \App\Models\Business::active()->with('category', 'area')
        ->where('is_featured', true)
        ->limit(6)
        ->get();
    if ($featured->count() < 3) {
        $featured = \App\Models\Business::active()->with('category', 'area')
            ->where('average_rating', '>', 0)
            ->orderByDesc('review_count')
            ->limit(6)
            ->get();
    }
@endphp
@if($featured->count())
<section class="bg-white border-y border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Featured Businesses</h2>
                <p class="text-sm text-slate-500 mt-1">Top picks in Churachandpur</p>
            </div>
            <a href="/businesses" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all →</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($featured as $biz)
                @include('partials._business-card', ['business' => $biz, 'variant' => 'photo'])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Recently Added --}}
@php
    $recent = \App\Models\Business::active()->with('category', 'area')->latest()->limit(6)->get();
@endphp
@if($recent->count())
<section class="max-w-6xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Recently Added</h2>
            <p class="text-sm text-slate-500 mt-1">New businesses in Churachandpur</p>
        </div>
        <a href="/businesses" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all →</a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($recent as $biz)
            @include('partials._business-card', ['business' => $biz, 'variant' => 'photo'])
        @endforeach
    </div>
</section>
@endif

{{-- Claim CTA --}}
<section class="max-w-6xl mx-auto px-4 py-10">
    <div class="bg-gradient-to-r from-primary-500 to-accent-500 rounded-2xl p-8 md:p-12 text-center text-white">
        <h2 class="text-2xl md:text-3xl font-bold mb-3">Own a Business?</h2>
        <p class="text-white/80 mb-6 max-w-lg mx-auto">List your business for free on Hola. Reach more customers in Churachandpur.</p>
        <a href="/admin" class="inline-block px-6 py-3 bg-white text-primary-600 rounded-xl font-semibold hover:bg-white/90 transition-colors">Claim Your Business →</a>
    </div>
</section>

{{-- Stats --}}
<section class="bg-white border-y border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-10">
        @php
            $totalBiz = \App\Models\Business::active()->count();
            $totalCats = \App\Models\Category::active()->count();
            $totalAreas = \App\Models\Area::active()->where('slug', '!=', 'other')->withCount('businesses')->get()->filter(fn($a) => $a->businesses_count > 0)->count();
            $totalReviews = \App\Models\Review::count();
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="stat-card rounded-xl border border-slate-100 p-5 text-center">
                <p class="text-2xl md:text-3xl font-bold text-primary-600">{{ $totalBiz }}+</p>
                <p class="text-sm text-slate-500 mt-1">Businesses</p>
            </div>
            <div class="stat-card rounded-xl border border-slate-100 p-5 text-center">
                <p class="text-2xl md:text-3xl font-bold text-accent-500">{{ $totalCats }}</p>
                <p class="text-sm text-slate-500 mt-1">Categories</p>
            </div>
            <div class="stat-card rounded-xl border border-slate-100 p-5 text-center">
                <p class="text-2xl md:text-3xl font-bold text-emerald-500">{{ $totalAreas }}</p>
                <p class="text-sm text-slate-500 mt-1">Areas</p>
            </div>
            <div class="stat-card rounded-xl border border-slate-100 p-5 text-center">
                <p class="text-2xl md:text-3xl font-bold text-amber-500">{{ $totalReviews }}</p>
                <p class="text-sm text-slate-500 mt-1">Reviews</p>
            </div>
        </div>
    </div>
</section>
@endsection
