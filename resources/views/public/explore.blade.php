@extends('layouts.public')

@section('title', 'Explore Businesses | Eiho One - Churachandpur')
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
        @if($launchControl->worldEnabled('shop') && $launchControl->moduleEnabled('catalog'))
            <a href="{{ route('explore', ['module' => 'ordering']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('module') === 'ordering' ? 'bg-primary-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-primary-300' }}">🛍️ Shopping</a>
        @endif
        @if($launchControl->worldEnabled('book') && $launchControl->moduleEnabled('bookings'))
            <a href="{{ route('explore', ['module' => 'booking']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('module') === 'booking' ? 'bg-primary-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-primary-300' }}">📅 Booking</a>
        @endif
        @if($launchControl->worldEnabled('ride') && $launchControl->moduleEnabled('transport'))
            <a href="{{ route('explore', ['module' => 'transport']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('module') === 'transport' ? 'bg-primary-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-primary-300' }}">🚗 Transport</a>
        @endif
        @if($launchControl->experienceEnabled('directory'))
            <a href="{{ route('explore', ['module' => 'directory']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('module') === 'directory' ? 'bg-primary-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-primary-300' }}">📍 Directory</a>
        @endif
    </div>
</div>

{{-- Map --}}
<div class="max-w-6xl mx-auto px-4 py-4">
    <button type="button" id="toggleMap" class="md:hidden w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700">
        Show map
    </button>
    <div id="mapPanel" class="hidden md:block">
        <div class="flex items-center justify-between gap-3 mb-2">
            <p class="text-sm text-slate-500"><span id="mapCount">{{ $mapBusinesses->count() }}</span> matching locations</p>
            <button type="button" id="locateMe" class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-sm font-medium text-slate-700 hover:border-primary-300">
                ◎ Use my location
            </button>
        </div>
        <div id="map" class="rounded-xl overflow-hidden border border-slate-200" style="height: 320px;"></div>
        <p id="locationStatus" class="mt-2 text-xs text-slate-500" aria-live="polite"></p>
    </div>
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
                    @foreach($categories as $cat)
                        <option value="{{ $cat->slug }}" {{ request('category') == $cat->slug ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="area" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 focus:outline-none focus:border-primary-300">
                    <option value="">All Areas</option>
                    @foreach($areas as $area)
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
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
@php
    $mapData = $mapBusinesses->map(fn($b) => [
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
    const businesses = {{ Illuminate\Support\Js::from($mapData) }};
    const mapPanel = document.getElementById('mapPanel');
    const toggleMap = document.getElementById('toggleMap');
    const locateButton = document.getElementById('locateMe');
    const locationStatus = document.getElementById('locationStatus');
    let map = null;

    toggleMap.addEventListener('click', function () {
        mapPanel.classList.toggle('hidden');
        toggleMap.textContent = mapPanel.classList.contains('hidden') ? 'Show map' : 'Hide map';
        window.setTimeout(() => map?.invalidateSize(), 50);
    });

    if (businesses.length === 0) {
        document.getElementById('map').classList.add('hidden');
        locateButton.classList.add('hidden');
        locationStatus.textContent = 'No matching businesses have map coordinates yet.';
        return;
    }

    if (typeof L === 'undefined') {
        locationStatus.textContent = 'The map could not load. Business results are still available below.';
        return;
    }

    map = L.map('map').setView([24.33, 93.70], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    const markers = [];
    const markerLayer = typeof L.markerClusterGroup === 'function'
        ? L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45 })
        : L.layerGroup();
    markerLayer.addTo(map);
    businesses.forEach(function (b) {
        const marker = L.marker([b.lat, b.lng]);
        const popup = document.createElement('div');
        const link = document.createElement('a');
        link.href = '/business/' + encodeURIComponent(b.slug);
        link.style.cssText = 'color:#2563eb;font-weight:600;text-decoration:none;';
        link.textContent = b.name;
        popup.appendChild(link);
        [b.category, b.address].filter(Boolean).forEach(function (line, index) {
            const detail = document.createElement('div');
            detail.style.cssText = 'font-size:12px;color:' + (index === 0 ? '#94a3b8' : '#64748b') + ';';
            detail.textContent = line;
            popup.appendChild(detail);
        });
        marker.bindPopup(popup);
        markerLayer.addLayer(marker);
        markers.push(marker);
    });

    if (markers.length > 1) {
        const group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    } else if (markers.length === 1) {
        map.setView([markers[0].getLatLng().lat, markers[0].getLatLng().lng], 15);
    }

    locateButton.addEventListener('click', function () {
        if (!navigator.geolocation) {
            locationStatus.textContent = 'Location is not supported by this browser.';
            return;
        }

        locateButton.disabled = true;
        locationStatus.textContent = 'Finding your location…';
        navigator.geolocation.getCurrentPosition(function (position) {
            const point = [position.coords.latitude, position.coords.longitude];
            L.circleMarker(point, {
                radius: 8,
                color: '#ffffff',
                weight: 3,
                fillColor: '#2563eb',
                fillOpacity: 1,
            }).addTo(map).bindPopup('You are here').openPopup();
            map.setView(point, 14);
            locationStatus.textContent = 'Map centered on your current location.';
            locateButton.disabled = false;
        }, function () {
            locationStatus.textContent = 'We could not access your location. Check browser permission and try again.';
            locateButton.disabled = false;
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
    });
});
</script>
@endsection
