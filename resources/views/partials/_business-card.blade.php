@props(['business', 'variant' => 'photo'])

@php
    $photo = null;
    $primary = $business->primaryPhoto();
    if ($primary) {
        $photo = str_starts_with($primary, 'http') ? $primary : asset($primary);
    }

    $priceRange = $business->price_range ?? null;
    $priceLabels = [1 => '₹', 2 => '₹₹', 3 => '₹₹₹'];

    $isOpen = null;
    $formattedCloseTime = null;
    if ($business->working_hours && is_array($business->working_hours)) {
        $dayOfWeek = strtolower(now()->format('l'));
        $todayHours = $business->working_hours[$dayOfWeek] ?? null;
        if ($todayHours && !empty($todayHours['open']) && !empty($todayHours['close'])) {
            try {
                $now = now();
                $opensAt = \Carbon\Carbon::parse($todayHours['open']);
                $closesAt = \Carbon\Carbon::parse($todayHours['close']);

                if ($closesAt->lessThanOrEqualTo($opensAt)) {
                    $now->lessThan($opensAt) ? $opensAt->subDay() : $closesAt->addDay();
                }

                $isOpen = $now->betweenIncluded($opensAt, $closesAt);
                $formattedCloseTime = $closesAt->format('g:i A');
            } catch (\Throwable) {
                $isOpen = null;
            }
        }
    }
@endphp

@if($variant === 'photo')
    {{-- Photo Card (for grid layouts) --}}
    <div class="business-card group bg-white rounded-2xl border border-slate-100 overflow-hidden hover:border-primary-200 hover:shadow-lg hover:shadow-primary-500/5 transition-all duration-300">
        {{-- Photo --}}
        <a href="/business/{{ $business->slug }}" class="block h-48 bg-slate-100 relative overflow-hidden">
            @if($photo)
                <img src="{{ $photo }}" alt="{{ $business->name }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy">
            @else
                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
                    <span class="text-5xl opacity-50">📍</span>
                </div>
            @endif
            {{-- Gradient overlay --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
            {{-- Badges --}}
            <div class="absolute top-3 left-3 flex gap-1.5">
                @if($business->claim_status === 'claimed')
                    <span class="px-2.5 py-1 rounded-full bg-emerald-500/90 text-white text-[11px] font-semibold shadow-lg backdrop-blur-sm">✓ Verified</span>
                @endif
                @if($business->is_featured)
                    <span class="px-2.5 py-1 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[11px] font-semibold shadow-lg">★ Featured</span>
                @endif
            </div>
            {{-- Rating on image --}}
            @if($business->average_rating > 0)
                <div class="absolute bottom-3 right-3 flex items-center gap-1 px-2 py-1 rounded-lg bg-black/50 backdrop-blur-sm">
                    <span class="text-amber-400 text-xs">★</span>
                    <span class="text-white text-xs font-semibold">{{ number_format($business->average_rating, 1) }}</span>
                </div>
            @endif
            {{-- Price Range --}}
            @if($priceRange)
                <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-white/90 text-slate-700 text-xs font-semibold shadow-lg backdrop-blur-sm">{{ $priceLabels[$priceRange] ?? '' }}</span>
            @endif
        </a>
        {{-- Info --}}
        <div class="p-4">
            <a href="/business/{{ $business->slug }}">
                <h3 class="text-base font-bold text-slate-900 truncate group-hover:text-primary-600 transition-colors mb-1">{{ $business->name }}</h3>
            </a>
            <div class="flex items-center gap-2 mb-2">
                @if($business->category)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-primary-50 text-primary-700 text-[11px] font-semibold">
                        <span>●</span>
                        {{ $business->category->name }}
                    </span>
                @endif
                @php
                    $capabilities = $business->effectiveModules();
                @endphp
                @if($capabilities['orders'] ?? false)
                    <span class="text-xs" title="Shopping">🛍️</span>
                @endif
                @if($capabilities['bookings'] ?? false)
                    <span class="text-xs" title="Booking">📅</span>
                @endif
                @if($capabilities['transport'] ?? false)
                    <span class="text-xs" title="Transport">🚗</span>
                @endif
            </div>
            <div class="flex items-center gap-1 text-xs text-slate-400">
                <span>📍</span>
                <span class="truncate">{{ $business->address ?: 'Churachandpur' }}</span>
            </div>
            @if($isOpen !== null)
                <div class="mt-2.5 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full {{ $isOpen ? 'bg-emerald-500 animate-pulse' : 'bg-red-400' }}"></span>
                    <span class="text-xs font-semibold {{ $isOpen ? 'text-emerald-600' : 'text-red-500' }}">{{ $isOpen ? 'Open Now' : 'Closed' }}</span>
                    @if($isOpen && $formattedCloseTime)
                        <span class="text-xs text-slate-400">· Closes {{ $formattedCloseTime }}</span>
                    @endif
                </div>
            @endif
            {{-- Book / Call / WhatsApp CTA (matches app booking capability) --}}
            @php
                $bookCap = $business->bookingCapability();
            @endphp
            @if(($bookCap['can_book_online'] ?? false) || $business->phone || $business->whatsapp)
                <div class="mt-3 flex gap-2">
                    @if($bookCap['can_book_online'] ?? false)
                        <a href="/business/{{ $business->slug }}?action=book" class="flex-1 text-center px-3 py-2 rounded-xl bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors">
                            Book Now
                        </a>
                    @elseif($business->whatsapp)
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $business->whatsapp) }}" target="_blank" rel="noopener" class="flex-1 text-center px-3 py-2 rounded-xl bg-emerald-500 text-white text-xs font-semibold hover:bg-emerald-600 transition-colors">
                            WhatsApp
                        </a>
                        @if($business->phone)
                            <a href="tel:{{ $business->phone }}" class="flex-1 text-center px-3 py-2 rounded-xl bg-slate-800 text-white text-xs font-semibold hover:bg-slate-700 transition-colors">
                                Call
                            </a>
                        @endif
                    @elseif($business->phone)
                        <a href="tel:{{ $business->phone }}" class="flex-1 text-center px-3 py-2 rounded-xl bg-slate-800 text-white text-xs font-semibold hover:bg-slate-700 transition-colors">
                            Call Now
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

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
