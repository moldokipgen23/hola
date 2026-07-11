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
{{-- Breadcrumb --}}
<div class="bg-white border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-3">
        <div class="flex items-center gap-2 text-xs text-slate-400">
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
            {{-- Photo Gallery --}}
            @if(!empty($business->photos) && count($business->photos) > 0)
                <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
                    <div class="h-64 md:h-80 bg-slate-100 relative" onclick="openLightbox(0)">
                        <img src="{{ str_starts_with($business->photos[0], 'http') ? $business->photos[0] : asset($business->photos[0]) }}" alt="{{ $business->name }}" class="w-full h-full object-cover cursor-pointer">
                        <span class="absolute bottom-3 right-3 px-2 py-1 rounded-lg bg-black/50 text-white text-xs">{{ count($business->photos) }} photos</span>
                    </div>
                    @if(count($business->photos) > 1)
                        <div class="flex gap-2 p-3 overflow-x-auto">
                            @foreach($business->photos as $i => $photo)
                                <button onclick="openLightbox({{ $i }})" class="w-16 h-16 rounded-lg overflow-hidden flex-shrink-0 border-2 {{ $i === 0 ? 'border-primary-500' : 'border-transparent' }} hover:border-primary-300 transition">
                                    <img src="{{ str_starts_with($photo, 'http') ? $photo : asset($photo) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- Header + Trust --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h1 class="text-2xl font-bold text-slate-900">{{ $business->name }}</h1>
                            @if($business->claim_status === 'claimed')
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">✓ Verified</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
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
                        <div class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-100">
                            <span class="text-xl font-bold text-emerald-700">★ {{ number_format($business->average_rating, 1) }}</span>
                            <span class="text-xs text-emerald-600">({{ $business->review_count }})</span>
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    <span>{{ $business->address }}</span>
                </div>
                {{-- Open/Closed --}}
                @if($business->working_hours && is_array($business->working_hours))
                    @php
                        $dayOfWeek = strtolower(now()->format('l'));
                        $todayHours = $business->working_hours[$dayOfWeek] ?? null;
                        $isOpen = null;
                        if ($todayHours && !empty($todayHours['open']) && !empty($todayHours['close'])) {
                            $now = now()->format('H:i');
                            $isOpen = $now >= $todayHours['open'] && $now <= $todayHours['close'];
                        }
                    @endphp
                    @if($isOpen !== null)
                        <div class="mt-2 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $isOpen ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                            <span class="text-sm font-medium {{ $isOpen ? 'text-emerald-600' : 'text-red-500' }}">{{ $isOpen ? 'Open Now' : 'Closed' }}</span>
                            <span class="text-xs text-slate-400">· Closes {{ $todayHours['close'] }}</span>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white rounded-xl border border-slate-100 p-4">
                <div class="flex gap-3">
                    @if($business->phone)
                        <a href="tel:{{ $business->phone }}" class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl bg-primary-50 text-primary-600 text-sm font-semibold hover:bg-primary-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            Call
                        </a>
                    @endif
                    @if($business->latitude && $business->longitude)
                        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $business->latitude }},{{ $business->longitude }}" target="_blank" class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl bg-primary-50 text-primary-600 text-sm font-semibold hover:bg-primary-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            Directions
                        </a>
                    @endif
                    <button onclick="shareBusiness()" class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl bg-slate-50 text-slate-600 text-sm font-semibold hover:bg-slate-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Share
                    </button>
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
                    <div class="space-y-0">
                        @foreach($business->working_hours as $day => $hours)
                            @php
                                $isToday = strtolower(now()->format('l')) === strtolower($day);
                            @endphp
                            <div class="flex justify-between text-sm py-2.5 {{ $isToday ? 'bg-primary-50 -mx-6 px-6 rounded-lg' : '' }} {{ !$loop->last ? 'border-b border-slate-50' : '' }}">
                                <span class="{{ $isToday ? 'text-primary-700 font-medium' : 'text-slate-500' }}">{{ ucfirst($day) }}{{ $isToday ? ' (Today)' : '' }}</span>
                                <span class="{{ $isToday ? 'text-primary-700 font-medium' : 'text-slate-900' }}">{{ $hours['open'] ?? 'Closed' }}{{ isset($hours['close']) ? ' - ' . $hours['close'] : '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Services (UC-style service packages) --}}
            @if($business->services && $business->services->count())
                <div class="bg-white rounded-xl border border-slate-100 p-6">
                    <h2 class="text-base font-semibold text-slate-900 mb-4">Services</h2>
                    <div class="space-y-3">
                        @foreach($business->services->where('is_active', true) as $service)
                            <div class="flex items-center justify-between p-4 rounded-xl border border-slate-100 hover:border-primary-200 transition">
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $service->name }}</h3>
                                    @if($service->description)
                                        <p class="text-xs text-slate-500 mt-0.5">{{ \Illuminate\Support\Str::limit($service->description, 80) }}</p>
                                    @endif
                                    <div class="flex items-center gap-2 mt-1">
                                        @if($service->duration)
                                            <span class="text-xs text-slate-400">⏱ {{ $service->duration }} min</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right ml-4">
                                    @if($service->price)
                                        <p class="text-sm font-bold text-slate-900">₹{{ number_format($service->price, 0) }}</p>
                                    @else
                                        <p class="text-xs text-slate-400">Get Quote</p>
                                    @endif
                                    @if($business->is_bookable)
                                        <button class="mt-1 px-3 py-1 rounded-lg bg-primary-500 text-white text-xs font-medium hover:bg-primary-600 transition-colors">Book</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Reviews --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-slate-900">Reviews ({{ $business->review_count }})</h2>
                </div>
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
                        <iframe width="100%" height="100%" style="border:0" loading="lazy"
                            src="https://maps.google.com/maps?q={{ $business->latitude }},{{ $business->longitude }}&z=15&output=embed"></iframe>
                    </div>
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $business->latitude }},{{ $business->longitude }}" target="_blank"
                        class="block w-full text-center mt-3 px-4 py-2.5 rounded-lg bg-primary-500 text-white text-sm font-medium hover:bg-primary-600 transition-colors">Get Directions</a>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Contact Card --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-4">Contact</h2>
                <div class="space-y-2">
                    @if($business->phone)
                        <a href="tel:{{ $business->phone }}" class="flex items-center gap-3 p-3 rounded-lg border border-slate-100 hover:border-primary-200 transition">
                            <span class="w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center text-primary-500 text-sm">📞</span>
                            <div>
                                <p class="text-xs text-slate-400">Phone</p>
                                <p class="text-sm font-medium text-slate-900">{{ $business->phone }}</p>
                            </div>
                        </a>
                    @endif
                    @if($business->whatsapp)
                        <a href="https://wa.me/{{ str_replace(['+', ' ', '-'], '', $business->whatsapp) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-lg border border-slate-100 hover:border-primary-200 transition">
                            <span class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center text-green-500 text-sm">💬</span>
                            <div>
                                <p class="text-xs text-slate-400">WhatsApp</p>
                                <p class="text-sm font-medium text-slate-900">{{ $business->whatsapp }}</p>
                            </div>
                        </a>
                    @endif
                    @if($business->email)
                        <a href="mailto:{{ $business->email }}" class="flex items-center gap-3 p-3 rounded-lg border border-slate-100 hover:border-primary-200 transition">
                            <span class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 text-sm">📧</span>
                            <div>
                                <p class="text-xs text-slate-400">Email</p>
                                <p class="text-sm font-medium text-slate-900">{{ $business->email }}</p>
                            </div>
                        </a>
                    @endif
                    @if($business->website)
                        <a href="{{ $business->website }}" target="_blank" class="flex items-center gap-3 p-3 rounded-lg border border-slate-100 hover:border-primary-200 transition">
                            <span class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center text-purple-500 text-sm">🌐</span>
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
                <div class="bg-gradient-to-br from-primary-50 to-accent-50 rounded-xl border border-primary-100 p-6 text-center">
                    <p class="text-2xl mb-2">🏢</p>
                    <p class="text-sm text-primary-700 font-semibold mb-1">Is this your business?</p>
                    <p class="text-xs text-primary-600 mb-4">Claim it to update your listing, respond to reviews, and manage your presence.</p>
                    <a href="/claim/{{ $business->id }}" class="block w-full px-4 py-2.5 rounded-lg bg-primary-500 text-white text-sm font-semibold hover:bg-primary-600 transition-colors">Claim This Business</a>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Photo Lightbox --}}
<div id="lightbox" class="fixed inset-0 z-[100] bg-black/90 hidden items-center justify-center" onclick="closeLightbox()">
    <button onclick="closeLightbox()" class="absolute top-4 right-4 text-white/80 hover:text-white z-10">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <button onclick="prevPhoto(event)" class="absolute left-4 text-white/80 hover:text-white z-10">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <button onclick="nextPhoto(event)" class="absolute right-4 text-white/80 hover:text-white z-10">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <img id="lightboxImg" src="" alt="" class="max-h-[85vh] max-w-[90vw] object-contain rounded-lg" onclick="event.stopPropagation()">
    <p id="lightboxCounter" class="text-white/60 text-sm mt-3"></p>
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

@push('scripts')
<script>
const photos = {!! json_encode(!empty($business->photos) ? collect($business->photos)->map(fn($p) => str_starts_with($p, 'http') ? $p : asset($p))->values()->toArray() : []) !!};
let currentPhoto = 0;

function openLightbox(index) {
    if (!photos.length) return;
    currentPhoto = index;
    updateLightbox();
    document.getElementById('lightbox').classList.remove('hidden');
    document.getElementById('lightbox').classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').classList.add('hidden');
    document.getElementById('lightbox').classList.remove('flex');
    document.body.style.overflow = '';
}

function nextPhoto(e) {
    e.stopPropagation();
    currentPhoto = (currentPhoto + 1) % photos.length;
    updateLightbox();
}

function prevPhoto(e) {
    e.stopPropagation();
    currentPhoto = (currentPhoto - 1 + photos.length) % photos.length;
    updateLightbox();
}

function updateLightbox() {
    document.getElementById('lightboxImg').src = photos[currentPhoto];
    document.getElementById('lightboxCounter').textContent = (currentPhoto + 1) + ' / ' + photos.length;
}

document.addEventListener('keydown', (e) => {
    if (document.getElementById('lightbox').classList.contains('hidden')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowRight') { currentPhoto = (currentPhoto + 1) % photos.length; updateLightbox(); }
    if (e.key === 'ArrowLeft') { currentPhoto = (currentPhoto - 1 + photos.length) % photos.length; updateLightbox(); }
});

function shareBusiness() {
    if (navigator.share) {
        navigator.share({ title: '{{ $business->name }}', url: window.location.href });
    } else {
        navigator.clipboard.writeText(window.location.href).then(() => {
            const btn = event.target.closest('button');
            const original = btn.innerHTML;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copied!';
            btn.classList.add('text-emerald-600');
            setTimeout(() => { btn.innerHTML = original; btn.classList.remove('text-emerald-600'); }, 2000);
        });
    }
}
</script>
@endpush
