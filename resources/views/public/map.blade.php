@extends('layouts.public')

@section('title', 'Business Map | Eiho One - Churachandpur Directory')
@section('description', 'Browse local businesses on an interactive map in Lamka, Churachandpur, Manipur, India')

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mb-2">Business Map</h1>
        <p class="text-slate-500 text-sm">Explore businesses near you on the interactive map</p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-end mb-3">
        <button type="button" id="locateMe" class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-sm font-medium text-slate-700 hover:border-primary-300">
            ◎ Use my location
        </button>
    </div>
    <div id="map" class="rounded-xl overflow-hidden border border-slate-200" style="height: 600px;"></div>
    <p class="mt-4 text-sm text-slate-500">Showing {{ $businesses->count() }} businesses with location data</p>
    <p id="locationStatus" class="mt-1 text-xs text-slate-500" aria-live="polite"></p>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
@php
    $mapData = $businesses->map(fn($b) => [
        'name' => $b->name,
        'slug' => $b->slug,
        'lat' => $b->latitude,
        'lng' => $b->longitude,
        'category' => $b->category?->name,
        'address' => $b->address,
    ])->values()->toArray();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const businesses = {{ Illuminate\Support\Js::from($mapData) }};
    const locateButton = document.getElementById('locateMe');
    const locationStatus = document.getElementById('locationStatus');

    if (typeof L === 'undefined') {
        locationStatus.textContent = 'The map could not load. You can still browse businesses from Explore.';
        locateButton.disabled = true;
        return;
    }

    const map = L.map('map').setView([24.33, 93.70], 13);

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
        if (!b.lat || !b.lng) return;
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
