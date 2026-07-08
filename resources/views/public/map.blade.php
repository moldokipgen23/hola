@extends('layouts.public')

@section('title', 'Business Map | ' . config('app.name', 'Hola'))
@section('description', 'Browse local businesses on an interactive map in Lamka, Churachandpur, Manipur, India')

@section('content')
<h1 class="text-3xl font-bold text-white mb-2">Business Map</h1>
<p class="text-slate-400 mb-6">Explore businesses near you on the interactive map</p>

<div id="map" class="rounded-xl overflow-hidden border border-white/10" style="height: 600px;"></div>

<div class="mt-6 text-sm text-slate-500">
    Showing {{ $businesses->count() }} businesses with location data
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const businesses = @json($businesses->map(fn($b) => [
        'name' => $b->name,
        'slug' => $b->slug,
        'lat' => $b->latitude,
        'lng' => $b->longitude,
        'category' => $b->category?->name,
        'address' => $b->address,
    ]));

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
            '<a href="/business/' + b.slug + '" style="color:#3b82f6;font-weight:600;text-decoration:none;">' + b.name + '</a>' +
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
