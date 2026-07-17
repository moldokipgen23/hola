@extends('layouts.public')

@section('title', 'Explore Businesses | Hola - Churachandpur')
@section('description', 'Discover and explore local businesses in Lamka, Churachandpur. Shop, book services, or find what you need nearby.')

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mb-2">Explore Businesses</h1>
        <p class="text-slate-500 text-sm">Discover everything in Churachandpur — shop, book, or just explore</p>
    </div>
</div>

{{-- Module Type Tabs --}}
<div class="max-w-6xl mx-auto px-4 -mt-0">
    <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
        <a href="{{ route('explore') }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ !request('module') ? 'bg-primary-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-primary-300' }}">
            All
        </a>
        <a href="{{ route('explore', ['module' => 'ordering']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('module') === 'ordering' ? 'bg-primary-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-primary-300' }}">
            🛍️ Shopping
        </a>
        <a href="{{ route('explore', ['module' => 'booking']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('module') === 'booking' ? 'bg-primary-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-primary-300' }}">
            📅 Booking
        </a>
        <a href="{{ route('explore', ['module' => 'directory']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('module') === 'directory' ? 'bg-primary-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-primary-300' }}">
            📍 Directory
        </a>
    </div>
</div>

{{-- Map --}}
<div class="max-w-6xl mx-auto px-4 py-4">
    <div id="map" class="rounded-xl overflow-hidden border border-slate-200" style="height: 400px;"></div>
</div>

{{-- Filters + Results --}}
<div class="max-w-6xl mx-auto px-4 py-4">
    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-6">
        @if(request('module'))
            <input type="hidden" name="module" value="{{ request('module') }}">
        @endif
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
                    @foreach(\App\Models\Area::active()->where('slug', '!=', 'other')->withCount('businesses')->orderBy('name')->get()->filter(fn($a) => $a->businesses_count > 0) as $area)
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

    <p class="text-sm text-slate-500 mb-4">{{ $businesses->total() }} {{ Str::plural('business', $businesses->total()) }} found</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($businesses as $biz)
            @include('partials._business-card', ['business' => $biz, 'variant' => 'photo'])
        @empty
            <div class="col-span-full text-center py-16">
                <p class="text-4xl mb-3">🔍</p>
                <p class="text-slate-500 text-lg font-medium">No businesses found</p>
                <p class="text-slate-400 text-sm mt-1">Try different search terms or filters</p>
                <a href="{{ route('explore') }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-primary-50 text-primary-600 text-sm font-medium hover:bg-primary-100">Clear filters</a>
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $businesses->withQueryString()->links() }}
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@php
    $mapData = $businesses->map(fn($b) => [
        'name' => $b->name,
        'slug' => $b->slug,
        'lat' => $b->latitude,
        'lng' => $b->longitude,
        'category' => $b->category?->name,
        'address' => $b->address,
    ])->filter(fn($b) => $b['lat'] && $b['lng'])->values()->toArray();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const businesses = {!! json_encode($mapData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!};

    if (businesses.length === 0) {
        document.getElementById('map').classList.add('hidden');
        return;
    }

    const map = L.map('map').setView([24.33, 93.70], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    const markers = [];
    businesses.forEach(function (b) {
        const marker = L.marker([b.lat, b.lng]).addTo(map);
        marker.bindPopup(
            '<a href="/business/' + b.slug + '" style="color:#0d9488;font-weight:600;text-decoration:none;">' + b.name + '</a>' +
            (b.category ? '<br><span style="font-size:12px;color:#94a3b8;">' + b.category + '</span>' : '') +
            (b.address ? '<br><span style="font-size:12px;color:#64748b;">' + b.address + '</span>' : '')
        );
        markers.push(marker);
    });

    if (markers.length > 1) {
        const group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    } else if (markers.length === 1) {
        map.setView([markers[0].getLatLng().lat, markers[0].getLatLng().lng], 15);
    }
});
</script>
@endsection
