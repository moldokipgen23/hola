@extends('layouts.public')

@section('title', 'Business Map | Hola - Churachandpur Directory')
@section('description', 'Browse local businesses on an interactive map in Lamka, Churachandpur, Manipur, India')

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mb-2">Business Map</h1>
        <p class="text-slate-500 text-sm">Explore businesses near you on the interactive map</p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-6">
    <div id="map" class="rounded-xl overflow-hidden border border-slate-200" style="height: 600px;"></div>
    <p class="mt-4 text-sm text-slate-500">Showing {{ $businesses->count() }} businesses with location data</p>
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
    ])->values()->toArray();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const businesses = {!! json_encode($mapData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!};

    const map = L.map('map').setView([24.33, 93.70], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    const markers = [];
    businesses.forEach(function (b) {
        if (!b.lat || !b.lng) return;
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
