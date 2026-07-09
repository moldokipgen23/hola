@extends('layouts.public')

@php
    $metaDescription = $business->description ?: "Find {$business->name} at {$business->address}. Call " . ($business->phone ?: 'now') . ".";
    $ogDescription = $business->description ?: "Find {$business->name} at {$business->address}";
@endphp
@section('title', $business->name . ' | ' . config('app.name', 'Hola'))
@section('description', \Illuminate\Support\Str::limit($metaDescription, 160))
@section('og_title', $business->name)
@section('og_description', $ogDescription)
@if($business->photos && count($business->photos) > 0)
    @section('og_image', asset($business->photos[0]))
@endif

@section('content')
<!-- Breadcrumb -->
<div class="text-sm text-slate-500 mb-6">
    <a href="/" class="hover:text-white">Home</a>
    <span class="mx-2">›</span>
    @if($business->category)
        <a href="/category/{{ $business->category->slug }}" class="hover:text-white">{{ $business->category->name }}</a>
        <span class="mx-2">›</span>
    @endif
    <span class="text-white">{{ $business->name }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Photos -->
        @if($business->photos && count($business->photos) > 0)
            <div class="glass-card p-4 rounded-xl">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    @foreach($business->photos as $photo)
                        <div class="rounded-lg overflow-hidden bg-slate-800 aspect-square">
                            <img src="{{ asset($photo) }}" alt="{{ $business->name }}" class="w-full h-full object-cover">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Header -->
        <div class="glass-card p-6 rounded-xl">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ $business->name }}</h1>
                    @if($business->category)
                        <a href="/category/{{ $business->category->slug }}" class="text-blue-400 text-sm hover:underline">{{ $business->category->name }}</a>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if($business->is_featured)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-500/20 text-yellow-400">Featured</span>
                    @endif
                    @if($business->average_rating)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-500/20 text-green-400">★ {{ $business->average_rating }}</span>
                    @endif
                </div>
            </div>
            <p class="text-slate-400">{{ $business->address }}</p>
        </div>

        <!-- Description -->
        @if($business->description)
            <div class="glass-card p-6 rounded-xl">
                <h2 class="text-lg font-semibold text-white mb-3">About</h2>
                <p class="text-slate-400 whitespace-pre-line">{{ $business->description }}</p>
            </div>
        @endif

        <!-- Working Hours -->
        @if($business->working_hours)
            <div class="glass-card p-6 rounded-xl">
                <h2 class="text-lg font-semibold text-white mb-3">Working Hours</h2>
                <div class="space-y-2">
                    @foreach($business->working_hours as $day => $hours)
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-400">{{ ucfirst($day) }}</span>
                            <span class="text-white">{{ $hours['open'] ?? 'Closed' }} - {{ $hours['close'] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Reviews -->
        <div class="glass-card p-6 rounded-xl">
            <h2 class="text-lg font-semibold text-white mb-4">Reviews ({{ $business->review_count }})</h2>
            @if($business->average_rating)
                <div class="flex items-center gap-4 mb-6">
                    <div class="text-4xl font-bold text-white">{{ $business->average_rating }}</div>
                    <div>
                        <div class="flex text-yellow-400 text-lg">
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= floor($business->average_rating))★@else☆@endif
                            @endfor
                        </div>
                        <p class="text-slate-500 text-sm">{{ $business->review_count }} reviews</p>
                    </div>
                </div>
            @endif

            @forelse($business->reviews()->with('user')->latest()->limit(10)->get() as $review)
                <div class="border-t border-white/5 pt-4 mt-4">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                            {{ strtoupper(substr($review->user->name, 0, 1)) }}
                        </div>
                        <span class="text-white text-sm font-medium">{{ $review->user->name }}</span>
                        <span class="text-yellow-400 text-sm">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                    </div>
                    @if($review->comment)
                        <p class="text-slate-400 text-sm">{{ $review->comment }}</p>
                    @endif
                    <p class="text-slate-600 text-xs mt-1">{{ $review->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <p class="text-slate-500 text-sm">No reviews yet.</p>
            @endforelse
        </div>

        <!-- Map -->
        @if($business->latitude && $business->longitude)
            <div class="glass-card p-6 rounded-xl">
                <h2 class="text-lg font-semibold text-white mb-3">Location</h2>
                <div class="rounded-xl overflow-hidden" style="height: 300px;">
                    <iframe
                        width="100%"
                        height="100%"
                        style="border:0"
                        loading="lazy"
                        src="https://maps.google.com/maps?q={{ $business->latitude }},{{ $business->longitude }}&z=15&output=embed">
                    </iframe>
                </div>
                <div class="flex gap-2 mt-3">
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $business->latitude }},{{ $business->longitude }}" target="_blank" class="btn-primary text-sm flex-1 text-center">Get Directions</a>
                </div>
            </div>
        @endif
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Contact -->
        <div class="glass-card p-6 rounded-xl">
            <h2 class="text-lg font-semibold text-white mb-4">Contact</h2>
            <div class="space-y-3">
                @if($business->phone)
                    <a href="tel:{{ $business->phone }}" class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition">
                        <span class="text-lg">📞</span>
                        <span class="text-white">{{ $business->phone }}</span>
                    </a>
                @endif
                @if($business->whatsapp)
                    <a href="https://wa.me/{{ str_replace(['+', ' ', '-'], '', $business->whatsapp) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition">
                        <span class="text-lg">💬</span>
                        <span class="text-white">{{ $business->whatsapp }}</span>
                    </a>
                @endif
                @if($business->email)
                    <a href="mailto:{{ $business->email }}" class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition">
                        <span class="text-lg">📧</span>
                        <span class="text-white">{{ $business->email }}</span>
                    </a>
                @endif
                @if($business->website)
                    <a href="{{ $business->website }}" target="_blank" class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition">
                        <span class="text-lg">🌐</span>
                        <span class="text-white">{{ $business->website }}</span>
                    </a>
                @endif
            </div>
        </div>

        <!-- Products -->
        @if($business->products->count())
            <div class="glass-card p-6 rounded-xl">
                <h2 class="text-lg font-semibold text-white mb-4">Products ({{ $business->products->count() }})</h2>
                <div class="space-y-2">
                    @foreach($business->products->take(5) as $product)
                        <div class="p-3 rounded-lg bg-white/5">
                            <p class="text-white text-sm font-medium">{{ $product->name }}</p>
                            @if($product->price)
                                <p class="text-green-400 text-sm">₹{{ number_format($product->price, 2) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Structured Data -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "LocalBusiness",
            "name": "{{ $business->name }}",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "{{ $business->address }}",
                "addressLocality": "{{ $business->locality ?? 'Lamka' }}",
                "addressRegion": "Churachandpur, Manipur",
                "addressCountry": "IN"
            },
            @if($business->phone)
            "telephone": "{{ $business->phone }}",
            @endif
            @if($business->website)
            "url": "{{ $business->website }}",
            @endif
            @if($business->latitude && $business->longitude)
            "geo": {
                "@type": "GeoCoordinates",
                "latitude": {{ $business->latitude }},
                "longitude": {{ $business->longitude }}
            },
            @endif
            @if($business->average_rating)
            "aggregateRating": {
                "@type": "AggregateRating",
                "ratingValue": {{ $business->average_rating }},
                "reviewCount": {{ $business->review_count }}
            },
            @endif
            "priceRange": "$$"
        }
        </script>
    </div>
</div>
@endsection
