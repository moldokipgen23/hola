@extends('layouts.public')

@php
    $metaDescription = $business->description ?: "Find {$business->name} at {$business->address}. Call " . ($business->phone ?: 'now') . ".";
    $ogDescription = $business->description ?: "Find {$business->name} at {$business->address}";
@endphp
@section('title', $business->name . ' | Hola - Churachandpur Directory')
@section('description', \Illuminate\Support\Str::limit($metaDescription, 160))
@section('og_title', $business->name)
@section('og_description', $ogDescription)
@if(!empty($business->photos) && count($business->photos) > 0)
    @section('og_image', str_starts_with($business->photos[0], 'http') ? $business->photos[0] : asset($business->photos[0]))
@endif

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-4">
        <div class="flex items-center gap-2 text-sm text-slate-400">
            <a href="/" class="hover:text-primary-600">Home</a>
            <span>/</span>
            @if($business->category)
                <a href="/category/{{ $business->category->slug }}" class="hover:text-primary-600">{{ $business->category->name }}</a>
                <span>/</span>
            @endif
            <span class="text-slate-600">{{ $business->name }}</span>
        </div>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Photos --}}
            @if(!empty($business->photos) && count($business->photos) > 0)
                <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
                    <div class="h-64 md:h-80 bg-slate-100 relative">
                        <img src="{{ str_starts_with($business->photos[0], 'http') ? $business->photos[0] : asset($business->photos[0]) }}" alt="{{ $business->name }}" class="w-full h-full object-cover">
                        @if($business->claim_status === 'claimed')
                            <span class="absolute top-4 left-4 px-3 py-1 rounded-full bg-emerald-500 text-white text-sm font-semibold shadow-sm">Verified</span>
                        @endif
                    </div>
                    @if(count($business->photos) > 1)
                        <div class="flex gap-2 p-3 overflow-x-auto">
                            @foreach($business->photos as $i => $photo)
                                <div class="w-16 h-16 rounded-lg overflow-hidden flex-shrink-0 border-2 {{ $i === 0 ? 'border-primary-500' : 'border-transparent' }}">
                                    <img src="{{ str_starts_with($photo, 'http') ? $photo : asset($photo) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- Header --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">{{ $business->name }}</h1>
                        <div class="flex items-center gap-2 mt-1">
                            @if($business->category)
                                <a href="/category/{{ $business->category->slug }}" class="px-2 py-0.5 rounded bg-primary-50 text-primary-700 text-xs font-medium">{{ $business->category->name }}</a>
                            @endif
                            @if($business->area)
                                <a href="/area/{{ $business->area->slug }}" class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-xs font-medium">{{ $business->area->name }}</a>
                            @endif
                            @if($business->is_featured)
                                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-xs font-medium">Featured</span>
                            @endif
                        </div>
                    </div>
                    @if($business->average_rating > 0)
                        <div class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-100">
                            <span class="text-lg font-bold text-emerald-700">★ {{ number_format($business->average_rating, 1) }}</span>
                            <span class="text-xs text-emerald-600">({{ $business->review_count }})</span>
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    <span>{{ $business->address }}</span>
                </div>
            </div>

            {{-- Description --}}
            @if($business->description)
                <div class="bg-white rounded-xl border border-slate-100 p-6">
                    <h2 class="text-base font-semibold text-slate-900 mb-3">About</h2>
                    <p class="text-sm text-slate-600 whitespace-pre-line leading-relaxed">{{ $business->description }}</p>
                </div>
            @endif

            {{-- Working Hours --}}
            @if($business->working_hours)
                <div class="bg-white rounded-xl border border-slate-100 p-6">
                    <h2 class="text-base font-semibold text-slate-900 mb-3">Working Hours</h2>
                    <div class="space-y-2">
                        @foreach($business->working_hours as $day => $hours)
                            <div class="flex justify-between text-sm py-1.5 {{ $loop->last ? '' : 'border-b border-slate-50' }}">
                                <span class="text-slate-500">{{ ucfirst($day) }}</span>
                                <span class="text-slate-900 font-medium">{{ $hours['open'] ?? 'Closed' }}{{ isset($hours['close']) ? ' - ' . $hours['close'] : '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Reviews --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-4">Reviews ({{ $business->review_count }})</h2>
                @if($business->average_rating > 0)
                    <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-100">
                        <div class="text-4xl font-bold text-slate-900">{{ number_format($business->average_rating, 1) }}</div>
                        <div>
                            <div class="flex text-amber-400 text-lg">
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= floor($business->average_rating))★@else☆@endif
                                @endfor
                            </div>
                            <p class="text-slate-400 text-sm">{{ $business->review_count }} reviews</p>
                        </div>
                    </div>
                @endif

                @forelse($business->reviews()->with('user')->latest()->limit(10)->get() as $review)
                    <div class="{{ !$loop->first ? 'border-t border-slate-100 pt-4 mt-4' : '' }}">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 text-xs font-bold">
                                {{ strtoupper(substr($review->user->name ?? 'U', 0, 1)) }}
                            </div>
                            <span class="text-slate-900 text-sm font-medium">{{ $review->user->name ?? 'User' }}</span>
                            <span class="text-amber-400 text-sm">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                            <span class="text-slate-400 text-xs ml-auto">{{ $review->created_at->diffForHumans() }}</span>
                        </div>
                        @if($review->comment)
                            <p class="text-slate-600 text-sm">{{ $review->comment }}</p>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-8">
                        <p class="text-3xl mb-2">💬</p>
                        <p class="text-slate-400 text-sm">No reviews yet</p>
                        <p class="text-slate-400 text-xs mt-1">Be the first to review this business</p>
                    </div>
                @endforelse
            </div>

            {{-- Map --}}
            @if($business->latitude && $business->longitude)
                <div class="bg-white rounded-xl border border-slate-100 p-6">
                    <h2 class="text-base font-semibold text-slate-900 mb-3">Location</h2>
                    <div class="rounded-lg overflow-hidden border border-slate-200" style="height: 300px;">
                        <iframe
                            width="100%"
                            height="100%"
                            style="border:0"
                            loading="lazy"
                            src="https://maps.google.com/maps?q={{ $business->latitude }},{{ $business->longitude }}&z=15&output=embed">
                        </iframe>
                    </div>
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $business->latitude }},{{ $business->longitude }}" target="_blank" class="block w-full text-center mt-3 px-4 py-2.5 rounded-lg bg-primary-500 text-white text-sm font-medium hover:bg-primary-600 transition-colors">Get Directions</a>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Contact --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-4">Contact</h2>
                <div class="space-y-2">
                    @if($business->phone)
                        <a href="tel:{{ $business->phone }}" class="flex items-center gap-3 p-3 rounded-lg border border-slate-100 hover:border-primary-200 transition">
                            <span class="w-8 h-8 rounded bg-primary-50 flex items-center justify-center text-primary-500 text-sm">📞</span>
                            <div>
                                <p class="text-xs text-slate-400">Phone</p>
                                <p class="text-sm font-medium text-slate-900">{{ $business->phone }}</p>
                            </div>
                        </a>
                    @endif
                    @if($business->whatsapp)
                        <a href="https://wa.me/{{ str_replace(['+', ' ', '-'], '', $business->whatsapp) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-lg border border-slate-100 hover:border-primary-200 transition">
                            <span class="w-8 h-8 rounded bg-green-50 flex items-center justify-center text-green-500 text-sm">💬</span>
                            <div>
                                <p class="text-xs text-slate-400">WhatsApp</p>
                                <p class="text-sm font-medium text-slate-900">{{ $business->whatsapp }}</p>
                            </div>
                        </a>
                    @endif
                    @if($business->email)
                        <a href="mailto:{{ $business->email }}" class="flex items-center gap-3 p-3 rounded-lg border border-slate-100 hover:border-primary-200 transition">
                            <span class="w-8 h-8 rounded bg-blue-50 flex items-center justify-center text-blue-500 text-sm">📧</span>
                            <div>
                                <p class="text-xs text-slate-400">Email</p>
                                <p class="text-sm font-medium text-slate-900">{{ $business->email }}</p>
                            </div>
                        </a>
                    @endif
                    @if($business->website)
                        <a href="{{ $business->website }}" target="_blank" class="flex items-center gap-3 p-3 rounded-lg border border-slate-100 hover:border-primary-200 transition">
                            <span class="w-8 h-8 rounded bg-purple-50 flex items-center justify-center text-purple-500 text-sm">🌐</span>
                            <div>
                                <p class="text-xs text-slate-400">Website</p>
                                <p class="text-sm font-medium text-slate-900 truncate">{{ $business->website }}</p>
                            </div>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Products --}}
            @if($business->products->count())
                <div class="bg-white rounded-xl border border-slate-100 p-6">
                    <h2 class="text-base font-semibold text-slate-900 mb-4">Products ({{ $business->products->count() }})</h2>
                    <div class="space-y-2">
                        @foreach($business->products->take(5) as $product)
                            <div class="p-3 rounded-lg border border-slate-100">
                                <p class="text-sm font-medium text-slate-900">{{ $product->name }}</p>
                                @if($product->price)
                                    <p class="text-sm font-semibold text-primary-600 mt-0.5">₹{{ number_format($product->price, 0) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Claim CTA --}}
            @if($business->claim_status === 'unclaimed')
                <div class="bg-primary-50 rounded-xl border border-primary-100 p-6 text-center">
                    <p class="text-sm text-primary-700 font-medium mb-2">Is this your business?</p>
                    <p class="text-xs text-primary-600 mb-4">Claim it to update your listing, respond to reviews, and manage your presence.</p>
                    <a href="/claim/{{ $business->id }}" class="block w-full px-4 py-2.5 rounded-lg bg-primary-500 text-white text-sm font-medium hover:bg-primary-600 transition-colors">Claim This Business</a>
                </div>
            @endif
        </div>
    </div>
</div>

@php
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $business->name,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $business->address,
            'addressLocality' => $business->locality ?? 'Lamka',
            'addressRegion' => 'Churachandpur, Manipur',
            'addressCountry' => 'IN',
        ],
        'priceRange' => '$$',
    ];
    if ($business->phone) $structuredData['telephone'] = $business->phone;
    if ($business->website) $structuredData['url'] = $business->website;
    if ($business->latitude && $business->longitude) {
        $structuredData['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $business->latitude, 'longitude' => (float) $business->longitude];
    }
    if ($business->average_rating > 0) {
        $structuredData['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (float) $business->average_rating, 'reviewCount' => (int) $business->review_count];
    }
@endphp
<script type="application/ld+json">
{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection
