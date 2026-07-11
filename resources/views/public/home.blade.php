@extends('layouts.public')

@section('title', 'Hola - Discover Local Businesses in Churachandpur')
@section('description', 'Find the best restaurants, shops, services, and businesses in Lamka, Churachandpur, Manipur. Browse by category, area, or search directly.')

@section('content')
{{-- HERO --}}
<section class="hero-gradient py-12 md:py-20">
    <div class="max-w-6xl mx-auto px-4 text-center">
        <h1 class="text-3xl md:text-5xl font-extrabold text-slate-900 mb-4 leading-tight">
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
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search restaurants, shops, services..."
                    class="w-full pl-12 pr-4 py-4 rounded-2xl border border-slate-200 bg-white text-slate-900 text-base shadow-lg shadow-slate-200/50 focus:outline-none search-glow transition-shadow"
                    autocomplete="off"
                >
                <div id="searchSpinner" class="hidden absolute right-4 top-1/2 -translate-y-1/2">
                    <svg class="animate-spin h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                </div>
            </div>

            {{-- Instant Search Dropdown --}}
            <div id="searchResults" class="hidden absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl border border-slate-200 shadow-xl shadow-slate-200/50 search-dropdown z-50">
            </div>
        </div>

        {{-- Popular Areas --}}
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <span class="text-sm text-slate-400 mr-1">Popular:</span>
            @php
                $areas = \App\Models\Area::active()->where('slug', '!=', 'other')->withCount('businesses')->where('businesses_count', '>', 0)->orderByDesc('businesses_count')->limit(4)->get();
            @endphp
            @foreach($areas as $area)
                <a href="/area/{{ $area->slug }}" class="px-3 py-1.5 rounded-full bg-white border border-slate-200 text-sm text-slate-600 hover:border-primary-300 hover:text-primary-600 hover:bg-primary-50 transition-colors">
                    {{ $area->name }} <span class="text-slate-400 text-xs">({{ $area->businesses_count }})</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- BROWSE BY CATEGORY --}}
<section class="max-w-6xl mx-auto px-4 py-12">
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
            'food' => '🍽️', 'restaurant' => '🍽️', 'restaurants' => '🍽️',
            'healthcare' => '🏥', 'hospital' => '🏥', 'clinic' => '🏥', 'pharmacy' => '💊',
            'shopping' => '🛍️', 'store' => '🛍️', 'shops' => '🛍️',
            'hotel' => '🏨', 'hotels' => '🏨', 'lodge' => '🏨',
            'electronics' => '📱', 'mobile' => '📱',
            'beauty' => '💇', 'salon' => '💇', 'spa' => '💇',
            'bank' => '🏦', 'banks' => '🏦', 'finance' => '🏦',
            'education' => '🏫', 'school' => '🏫', 'college' => '🏫',
            'automotive' => '🚗', 'garage' => '🚗',
            'sports' => '⚽', 'gym' => '💪', 'fitness' => '💪',
            'professional' => '💼', 'office' => '💼',
            'church' => '⛪', 'churches' => '⛪', 'worship' => '⛪',
            'preschool' => '🧒', 'nursery' => '🧒',
            'services' => '🔧', 'repair' => '🔧',
            'food' => '🍽️',
            'general' => '📦',
        ];
        $defaultIcon = '📍';
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach($categories as $cat)
            @php
                $icon = $defaultIcon;
                foreach($categoryIcons as $key => $emoji) {
                    if(stripos($cat->name, $key) !== false) { $icon = $emoji; break; }
                }
            @endphp
            <a href="/category/{{ $cat->slug }}" class="category-card bg-white rounded-xl border border-slate-100 p-4 text-center hover:border-primary-200">
                <div class="text-3xl mb-2">{{ $icon }}</div>
                <p class="text-sm font-semibold text-slate-800 truncate">{{ $cat->name }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ $cat->businesses_count }} {{ Str::plural('business', $cat->businesses_count) }}</p>
            </a>
        @endforeach
    </div>
</section>

{{-- EXPLORE BY AREA --}}
<section class="bg-white border-y border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Explore by Area</h2>
                <p class="text-sm text-slate-500 mt-1">Browse businesses in your neighborhood</p>
            </div>
            <a href="/areas" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all →</a>
        </div>

        @php
            $areasList = \App\Models\Area::active()->where('slug', '!=', 'other')->withCount('businesses')->where('businesses_count', '>', 0)->orderByDesc('businesses_count')->limit(6)->get();
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
    </div>
</section>

{{-- FEATURED BUSINESSES --}}
<section class="max-w-6xl mx-auto px-4 py-12">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Featured Businesses</h2>
            <p class="text-sm text-slate-500 mt-1">Top-rated businesses in Churachandpur</p>
        </div>
        <a href="/businesses" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all →</a>
    </div>

    @php
        $featured = \App\Models\Business::active()->where('is_featured', true)
            ->with('category')
            ->orderByDesc('average_rating')
            ->limit(6)
            ->get();
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($featured as $biz)
            <a href="/business/{{ $biz->slug }}" class="business-card bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-primary-200">
                {{-- Photo --}}
                <div class="h-40 bg-slate-100 relative overflow-hidden">
                    @if(!empty($biz->photos) && is_array($biz->photos) && count($biz->photos) > 0)
                        @php $photo = $biz->photos[0]; @endphp
                        <img src="{{ str_starts_with($photo, 'http') ? $photo : asset($photo) }}" alt="{{ $biz->name }}" class="w-full h-full object-cover" loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-50 to-accent-50">
                            <span class="text-4xl">📍</span>
                        </div>
                    @endif
                    @if($biz->is_featured)
                        <span class="absolute top-3 left-3 px-2 py-0.5 rounded-full bg-yellow-400 text-yellow-900 text-xs font-semibold">Featured</span>
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
                        <span class="inline-block px-2 py-0.5 rounded bg-primary-50 text-primary-700 text-xs font-medium mb-1">{{ $biz->category->name }}</span>
                    @endif
                    <p class="text-xs text-slate-400 truncate mt-1">📍 {{ $biz->address ?: 'Churachandpur' }}</p>
                </div>
            </a>
        @endforeach
    </div>

    @if($featured->isEmpty())
        <div class="text-center py-12 text-slate-400">
            <p class="text-lg mb-2">No featured businesses yet</p>
            <a href="/businesses" class="text-sm text-primary-600 hover:underline">Browse all businesses →</a>
        </div>
    @endif
</section>

{{-- RECENTLY ADDED --}}
<section class="bg-white border-y border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Recently Added</h2>
                <p class="text-sm text-slate-500 mt-1">New businesses in Churachandpur</p>
            </div>
            <a href="/businesses" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all →</a>
        </div>

        @php
            $recent = \App\Models\Business::active()->with('category', 'area')
                ->latest()
                ->limit(6)
                ->get();
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($recent as $biz)
                <a href="/business/{{ $biz->slug }}" class="business-card bg-white rounded-xl border border-slate-100 p-4 hover:border-primary-200">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 flex-shrink-0 overflow-hidden">
                            @if(!empty($biz->photos) && is_array($biz->photos) && count($biz->photos) > 0)
                                <img src="{{ str_starts_with($biz->photos[0], 'http') ? $biz->photos[0] : asset($biz->photos[0]) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-50 to-accent-50">
                                    <span class="text-lg">📍</span>
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-semibold text-slate-900 truncate">{{ $biz->name }}</h3>
                            <p class="text-xs text-slate-400 truncate mt-0.5">📍 {{ $biz->address ?: 'Churachandpur' }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                @if($biz->category)
                                    <span class="text-xs text-primary-600">{{ $biz->category->name }}</span>
                                @endif
                                @if($biz->area && $biz->area->slug !== 'other')
                                    <span class="text-xs text-slate-400">· {{ $biz->area->name }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- STATS --}}
<section class="max-w-6xl mx-auto px-4 py-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @php
            $totalBiz = \App\Models\Business::active()->count();
            $totalCats = \App\Models\Category::active()->count();
            $totalAreas = \App\Models\Area::active()->where('slug', '!=', 'other')->withCount('businesses')->where('businesses_count', '>', 0)->count();
            $totalReviews = \App\Models\Review::count();
        @endphp
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
</section>
@endsection

@section('scripts')
<script>
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const searchSpinner = document.getElementById('searchSpinner');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();

        if (q.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }

        searchSpinner.classList.remove('hidden');
        searchTimeout = setTimeout(() => {
            fetch(`/api/instant-search?q=${encodeURIComponent(q)}&limit=8`)
                .then(r => r.json())
                .then(data => {
                    searchSpinner.classList.add('hidden');
                    if (data.results && data.results.length > 0) {
                        let html = '';
                        data.results.forEach(b => {
                            html += `<a href="/business/${b.slug}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors border-b border-slate-50 last:border-0">
                                <div class="w-10 h-10 rounded-lg bg-slate-100 flex-shrink-0 overflow-hidden">
                                    ${b.photo ? `<img src="${b.photo}" alt="" class="w-full h-full object-cover">` : '<div class="w-full h-full flex items-center justify-center text-slate-400 text-sm">📍</div>'}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-900 truncate">${b.name}</p>
                                    <p class="text-xs text-slate-400 truncate">${b.category || ''} ${b.area ? '· ' + b.area : ''}</p>
                                </div>
                                ${b.rating ? `<span class="text-xs text-emerald-600 font-semibold">★ ${b.rating}</span>` : ''}
                            </a>`;
                        });
                        html += `<a href="/businesses?q=${encodeURIComponent(q)}" class="block px-4 py-3 text-sm text-primary-600 hover:bg-primary-50 text-center font-medium">See all results for "${q}" →</a>`;
                        searchResults.innerHTML = html;
                        searchResults.classList.remove('hidden');
                    } else {
                        searchResults.innerHTML = `<div class="px-4 py-6 text-center text-sm text-slate-400">No businesses found for "${q}"</div>`;
                        searchResults.classList.remove('hidden');
                    }
                })
                .catch(() => {
                    searchSpinner.classList.add('hidden');
                });
        }, 300);
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
        if (!document.getElementById('searchContainer').contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });

    // Enter key navigates to search page
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const q = searchInput.value.trim();
            if (q) window.location.href = `/businesses?q=${encodeURIComponent(q)}`;
        }
    });
</script>
@endsection
