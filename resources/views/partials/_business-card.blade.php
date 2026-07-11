@props(['business', 'variant' => 'photo'])

@php
    $photo = null;
    if (!empty($business->photos) && is_array($business->photos) && count($business->photos) > 0) {
        $p = $business->photos[0];
        $photo = str_starts_with($p, 'http') ? $p : asset($p);
    }

    $priceRange = $business->price_range ?? null;
    $priceLabels = [1 => '₹', 2 => '₹₹', 3 => '₹₹₹'];

    $isOpen = null;
    if ($business->working_hours && is_array($business->working_hours)) {
        $dayOfWeek = strtolower(now()->format('l'));
        $todayHours = $business->working_hours[$dayOfWeek] ?? null;
        if ($todayHours && !empty($todayHours['open']) && !empty($todayHours['close'])) {
            $now = now()->format('H:i');
            $isOpen = $now >= $todayHours['open'] && $now <= $todayHours['close'];
        }
    }
@endphp

@if($variant === 'photo')
    {{-- Photo Card (for grid layouts) --}}
    <a href="/business/{{ $business->slug }}" class="business-card bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-primary-200 group">
        {{-- Photo --}}
        <div class="h-44 bg-slate-100 relative overflow-hidden">
            @if($photo)
                <img src="{{ $photo }}" alt="{{ $business->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
            @else
                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-50 to-accent-50">
                    <span class="text-4xl">📍</span>
                </div>
            @endif
            {{-- Badges --}}
            <div class="absolute top-3 left-3 flex gap-1.5">
                @if($business->claim_status === 'claimed')
                    <span class="px-2 py-0.5 rounded-full bg-emerald-500 text-white text-xs font-semibold shadow-sm">✓ Verified</span>
                @endif
                @if($business->is_featured)
                    <span class="px-2 py-0.5 rounded-full bg-amber-500 text-white text-xs font-semibold shadow-sm">Featured</span>
                @endif
            </div>
            {{-- Price Range --}}
            @if($priceRange)
                <span class="absolute top-3 right-3 px-2 py-0.5 rounded-full bg-white/90 text-slate-700 text-xs font-semibold shadow-sm">{{ $priceLabels[$priceRange] ?? '' }}</span>
            @endif
        </div>
        {{-- Info --}}
        <div class="p-4">
            <div class="flex items-start justify-between gap-2 mb-1">
                <h3 class="text-sm font-semibold text-slate-900 truncate group-hover:text-primary-600 transition-colors">{{ $business->name }}</h3>
                @if($business->average_rating > 0)
                    <span class="flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold whitespace-nowrap">
                        ★ {{ number_format($business->average_rating, 1) }}
                    </span>
                @endif
            </div>
            @if($business->category)
                <span class="inline-block px-2 py-0.5 rounded bg-primary-50 text-primary-700 text-xs font-medium">{{ $business->category->name }}</span>
            @endif
            <p class="text-xs text-slate-400 truncate mt-2">📍 {{ $business->address ?: 'Churachandpur' }}</p>
            @if($isOpen !== null)
                <p class="text-xs mt-1.5 font-medium {{ $isOpen ? 'text-emerald-600' : 'text-red-500' }}">{{ $isOpen ? '● Open Now' : '● Closed' }}</p>
            @endif
        </div>
    </a>

@elseif($variant === 'compact')
    {{-- Compact Card (for horizontal lists, carousels) --}}
    <a href="/business/{{ $business->slug }}" class="business-card flex items-center gap-3 bg-white rounded-xl border border-slate-100 p-3 hover:border-primary-200 group">
        <div class="w-14 h-14 rounded-xl bg-slate-100 flex-shrink-0 overflow-hidden">
            @if($photo)
                <img src="{{ $photo }}" alt="{{ $business->name }}" class="w-full h-full object-cover" loading="lazy">
            @else
                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-50 to-accent-50 text-lg">📍</div>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
                <h3 class="text-sm font-semibold text-slate-900 truncate group-hover:text-primary-600 transition-colors">{{ $business->name }}</h3>
                @if($business->claim_status === 'claimed')
                    <span class="text-emerald-500 text-xs">✓</span>
                @endif
            </div>
            <p class="text-xs text-slate-400 truncate">{{ $business->category->name ?? '' }} {{ $business->address ? '· ' . Str::limit($business->address, 30) : '' }}</p>
            <div class="flex items-center gap-2 mt-1">
                @if($business->average_rating > 0)
                    <span class="text-xs text-emerald-600 font-semibold">★ {{ number_format($business->average_rating, 1) }}</span>
                @endif
                @if($priceRange)
                    <span class="text-xs text-slate-400">{{ $priceLabels[$priceRange] }}</span>
                @endif
                @if($isOpen !== null)
                    <span class="text-xs {{ $isOpen ? 'text-emerald-600' : 'text-red-500' }}">{{ $isOpen ? 'Open' : 'Closed' }}</span>
                @endif
            </div>
        </div>
        @if($business->average_rating > 0)
            <span class="flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold whitespace-nowrap flex-shrink-0">
                ★ {{ number_format($business->average_rating, 1) }}
            </span>
        @endif
    </a>
@endif
