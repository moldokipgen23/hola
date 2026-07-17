@extends('layouts.admin')

@section('title', $business->name)
@section('header', 'Business Detail')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.businesses') }}" class="text-slate-400 hover:text-white text-sm inline-flex items-center gap-1">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Businesses
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Header Card -->
        <div class="glass-card p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-white">{{ $business->name }}</h2>
                    <p class="text-slate-400 text-sm mt-1">{{ $business->address }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($business->is_active)
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="badge badge-red">Inactive</span>
                    @endif
                    @if($business->is_featured)
                        <span class="badge badge-yellow">Featured</span>
                    @endif
                    @if($business->verification_status === 'verified')
                        <span class="badge badge-green">Verified</span>
                    @elseif($business->verification_status === 'pending')
                        <span class="badge badge-yellow">Pending Verification</span>
                    @endif
                    @if($business->claim_status === 'claimed')
                        <span class="badge badge-blue">Claimed</span>
                    @endif
                </div>
            </div>

            @if($business->description)
                <p class="text-slate-300 text-sm leading-relaxed">{{ $business->description }}</p>
            @else
                <p class="text-slate-600 text-sm italic">No description provided.</p>
            @endif
        </div>

        <!-- Photos -->
        @if($business->photos && count($business->photos) > 0)
            <div class="glass-card p-6">
                <h3 class="text-white font-semibold mb-4">Photos ({{ count($business->photos) }})</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach($business->photos as $photo)
                        <div class="aspect-square rounded-xl overflow-hidden bg-white/5">
                            <img src="{{ str_starts_with($photo, 'http') ? $photo : asset($photo) }}" alt="Business photo" class="w-full h-full object-cover">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Business Info Grid -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Business Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Category</p>
                    <p class="text-white font-medium">{{ $business->category->name ?? '-' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Subcategory</p>
                    <p class="text-white font-medium">{{ $business->subcategory->name ?? '-' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Locality</p>
                    <p class="text-white font-medium">{{ $business->locality ?? '-' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">District</p>
                    <p class="text-white font-medium">{{ $business->district ?? '-' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Claim Status</p>
                    <p class="text-white font-medium capitalize">{{ str_replace('_', ' ', $business->claim_status ?? 'unclaimed') }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Verification</p>
                    <p class="text-white font-medium capitalize">{{ $business->verification_status ?? 'none' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Quality Score</p>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-2 bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $business->quality_score >= 70 ? 'bg-green-500' : ($business->quality_score >= 40 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $business->quality_score }}%"></div>
                        </div>
                        <span class="text-white font-medium text-sm">{{ $business->quality_score }}/100</span>
                    </div>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Source</p>
                    <p class="text-white font-medium">{{ $business->source ?? 'manual' }}</p>
                </div>
                @if($business->external_id)
                    <div class="bg-white/5 rounded-xl p-3 md:col-span-2">
                        <p class="text-xs text-slate-500 mb-1">External ID</p>
                        <p class="text-white font-medium text-xs">{{ $business->external_id }}</p>
                    </div>
                @endif
                @if($business->last_synced_at)
                    <div class="bg-white/5 rounded-xl p-3">
                        <p class="text-xs text-slate-500 mb-1">Last Synced</p>
                        <p class="text-white font-medium">{{ $business->last_synced_at->diffForHumans() }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Enabled Modules -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Modules & Features</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @php
                    $modules = $business->enabled_modules ?? [];
                    $moduleLabels = ['catalog' => 'Catalog', 'bookings' => 'Bookings', 'orders' => 'Orders', 'inventory' => 'Inventory'];
                @endphp
                @foreach($moduleLabels as $key => $label)
                    <div class="bg-white/5 rounded-xl p-3 text-center">
                        <p class="text-xs text-slate-500 mb-1">{{ $label }}</p>
                        @if($modules[$key] ?? false)
                            <span class="badge badge-green text-xs">Enabled</span>
                        @else
                            <span class="badge bg-slate-500/20 text-slate-400 text-xs">Disabled</span>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Service Type</p>
                    <p class="text-white font-medium capitalize">{{ $business->service_type ?? 'directory' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Bookable</p>
                    @if($business->is_bookable)
                        <span class="badge badge-green">Yes</span>
                    @else
                        <span class="badge bg-slate-500/20 text-slate-400">No</span>
                    @endif
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Price Range</p>
                    <p class="text-white font-medium">{{ $business->price_range ? str_repeat('₹', $business->price_range) : '—' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Products / Bookings / Orders</p>
                    <p class="text-white font-medium text-sm">{{ $business->products_count ?? 0 }}p / {{ $business->bookings_count ?? 0 }}b / {{ $business->orders_count ?? 0 }}o</p>
                </div>
            </div>
        </div>

        <!-- Claim Settings -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Claim Settings</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Notifications Enabled</p>
                    @if($business->claim_notifications_enabled)
                        <span class="badge badge-green">Yes</span>
                    @else
                        <span class="badge bg-slate-500/20 text-slate-400">No</span>
                    @endif
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Delay (days)</p>
                    <p class="text-white font-medium">{{ $business->claim_notification_delay_days ?? 3 }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Preferred Channel</p>
                    <p class="text-white font-medium capitalize">{{ $business->claim_preferred_channel ?? 'all' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Auto-Approve</p>
                    @if($business->claim_auto_approve)
                        <span class="badge badge-green">Yes</span>
                    @else
                        <span class="badge bg-slate-500/20 text-slate-400">No</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Contact Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if($business->phone)
                    <div class="flex items-center gap-3 bg-white/5 rounded-xl p-4">
                        <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-green-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Phone</p>
                            <a href="tel:{{ $business->phone }}" class="text-white text-sm font-medium hover:text-green-400">{{ $business->phone }}</a>
                        </div>
                    </div>
                @endif
                @if($business->whatsapp)
                    <div class="flex items-center gap-3 bg-white/5 rounded-xl p-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-emerald-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">WhatsApp</p>
                            <p class="text-white text-sm font-medium">{{ $business->whatsapp }}</p>
                        </div>
                    </div>
                @endif
                @if($business->email)
                    <div class="flex items-center gap-3 bg-white/5 rounded-xl p-4">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-blue-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Email</p>
                            <a href="mailto:{{ $business->email }}" class="text-white text-sm font-medium hover:text-blue-400">{{ $business->email }}</a>
                        </div>
                    </div>
                @endif
                @if($business->website)
                    <div class="flex items-center gap-3 bg-white/5 rounded-xl p-4">
                        <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-purple-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Website</p>
                            <a href="{{ $business->website }}" target="_blank" class="text-white text-sm font-medium hover:text-purple-400">{{ $business->website }}</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Working Hours -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Working Hours</h3>
            @if($business->working_hours && is_array($business->working_hours) && count($business->working_hours) > 0)
                <div class="space-y-2">
                    @foreach($business->working_hours as $day => $hours)
                        <div class="flex justify-between items-center bg-white/5 rounded-xl px-4 py-2.5">
                            <span class="text-white text-sm font-medium capitalize">{{ $day }}</span>
                            @if(is_array($hours))
                                @if(isset($hours['open']) && isset($hours['close']))
                                    <span class="text-slate-400 text-sm">{{ $hours['open'] }} - {{ $hours['close'] }}</span>
                                @else
                                    <span class="text-slate-500 text-sm">{{ json_encode($hours) }}</span>
                                @endif
                            @else
                                <span class="text-slate-400 text-sm">{{ $hours }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-600 text-sm italic">No working hours set.</p>
            @endif
        </div>

        <!-- Location / Map -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Location</h3>
            <div class="bg-white/5 rounded-xl p-4 mb-4">
                <p class="text-white text-sm">{{ $business->address }}</p>
                @if($business->locality || $business->district)
                    <p class="text-slate-400 text-xs mt-1">{{ trim(($business->locality ?? '') . ', ' . ($business->district ?? ''), ', ') }}</p>
                @endif
            </div>
            @if($business->latitude && $business->longitude)
                <div class="rounded-xl overflow-hidden bg-white/5" style="height: 250px;">
                    <iframe
                        width="100%"
                        height="100%"
                        style="border:0; filter: invert(90%) hue-rotate(180deg) brightness(1.1) contrast(0.9);"
                        loading="lazy"
                        src="https://maps.google.com/maps?q={{ $business->latitude }},{{ $business->longitude }}&z=15&output=embed">
                    </iframe>
                </div>
                <div class="mt-3 flex gap-2">
                    <a href="https://www.google.com/maps?q={{ $business->latitude }},{{ $business->longitude }}" target="_blank" class="btn-ghost text-sm flex-1 text-center">Open in Google Maps</a>
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $business->latitude }},{{ $business->longitude }}" target="_blank" class="btn-ghost text-sm flex-1 text-center">Get Directions</a>
                </div>
            @else
                <p class="text-slate-600 text-sm italic">No coordinates available.</p>
            @endif
        </div>

        <!-- Delivery Zones -->
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-white font-semibold">Delivery Zones ({{ $business->deliveryZones->count() }})</h3>
                <a href="{{ route('owner.businesses.delivery-zones', $business->id) }}" target="_blank" class="btn-ghost text-xs">Manage in Vendor Dashboard</a>
            </div>
            @if($business->deliveryZones->count())
                <div class="space-y-2">
                    @foreach($business->deliveryZones as $zone)
                        <div class="flex justify-between items-center bg-white/5 rounded-xl p-3">
                            <div>
                                <p class="text-white text-sm font-medium">{{ $zone->area?->name ?? 'Area #'.$zone->area_id }}</p>
                                <p class="text-slate-500 text-xs">Delivery Fee: ₹{{ number_format($zone->delivery_fee, 2) }}</p>
                            </div>
                            <div class="text-right text-xs text-slate-500">
                                @if($zone->pincodes)
                                    <p>{{ is_array($zone->pincodes) ? count($zone->pincodes) : 0 }} pincodes</p>
                                @endif
                                <span class="badge {{ $zone->is_active ? 'badge-green' : 'badge-red' }}">
                                    {{ $zone->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-600 text-sm italic">No delivery zones configured.</p>
            @endif
        </div>

        <!-- Products -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Products ({{ $business->products->count() }})</h3>
            @if($business->products->count())
                <div class="space-y-2">
                    @foreach($business->products as $product)
                        <div class="flex justify-between items-center bg-white/5 rounded-xl p-4">
                            <div>
                                <p class="text-white text-sm font-medium">{{ $product->name }}</p>
                                @if($product->description)
                                    <p class="text-slate-500 text-xs mt-0.5">{{ Str::limit($product->description, 100) }}</p>
                                @endif
                            </div>
                            <div class="text-right flex-shrink-0 ml-4">
                                @if($product->price)
                                    <p class="text-white text-sm font-semibold">₹{{ number_format($product->price, 2) }}</p>
                                @endif
                                <span class="badge {{ $product->availability === 'in_stock' ? 'badge-green' : ($product->availability === 'pre_order' ? 'badge-yellow' : 'badge-red') }}">
                                    {{ str_replace('_', ' ', ucfirst($product->availability ?? 'unknown')) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-600 text-sm italic">No products listed.</p>
            @endif
        </div>

        <!-- Reviews -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Reviews ({{ $business->reviews->count() }})</h3>
            @if($business->reviews->count())
                <div class="space-y-3">
                    @foreach($business->reviews as $review)
                        <div class="bg-white/5 rounded-xl p-4">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                                        {{ substr($review->user->name ?? 'A', 0, 1) }}
                                    </div>
                                    <span class="text-white text-sm font-medium">{{ $review->user->name ?? 'Anonymous' }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= $review->rating)
                                            <svg class="w-3.5 h-3.5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        @else
                                            <svg class="w-3.5 h-3.5 text-slate-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        @endif
                                    @endfor
                                    <span class="text-yellow-400 text-xs ml-1">{{ $review->rating }}/5</span>
                                </div>
                            </div>
                            @if($review->comment)
                                <p class="text-slate-300 text-sm">{{ $review->comment }}</p>
                            @endif
                            <p class="text-slate-600 text-xs mt-2">{{ $review->created_at->diffForHumans() }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-600 text-sm italic">No reviews yet.</p>
            @endif
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Actions -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Actions</h3>
            <div class="space-y-2">
                <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn-primary w-full text-center block">Edit Business</a>
                @if($business->latitude && $business->longitude)
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $business->latitude }},{{ $business->longitude }}" target="_blank" class="btn-ghost w-full text-center block">Get Directions</a>
                @endif
                <form method="POST" action="{{ route('admin.businesses.destroy', $business->id) }}" data-confirm="Delete this business permanently?" class="w-full">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger w-full">Delete Business</button>
                </form>
            </div>
        </div>

        <!-- Statistics -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Statistics</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 text-sm">Views</span>
                    <span class="text-white font-semibold">{{ number_format($business->views_count ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 text-sm">Saves</span>
                    <span class="text-blue-400 font-semibold">{{ number_format($business->saves_count ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 text-sm">Call Clicks</span>
                    <span class="text-green-400 font-semibold">{{ number_format($business->call_count ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 text-sm">WhatsApp Clicks</span>
                    <span class="text-emerald-400 font-semibold">{{ number_format($business->whatsapp_count ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 text-sm">Directions</span>
                    <span class="text-indigo-400 font-semibold">{{ number_format($business->directions_count ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 text-sm">Shares</span>
                    <span class="text-purple-400 font-semibold">{{ number_format($business->share_count ?? 0) }}</span>
                </div>
                <div class="border-t border-white/5 pt-3 flex justify-between items-center">
                    <span class="text-slate-400 text-sm">Rating</span>
                    <div class="flex items-center gap-1">
                        <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <span class="text-yellow-400 font-semibold">{{ number_format($business->average_rating ?? 0, 1) }}</span>
                        <span class="text-slate-500 text-xs">({{ $business->review_count ?? 0 }})</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Owner -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Owner</h3>
            @if($business->user)
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold">
                        {{ substr($business->user->name ?? 'U', 0, 1) }}
                    </div>
                    <div>
                        <p class="text-white text-sm font-medium">{{ $business->user->name }}</p>
                        <p class="text-slate-500 text-xs">{{ $business->user->email }}</p>
                    </div>
                </div>
            @elseif($business->createdBy)
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold">
                        {{ substr($business->createdBy->name ?? 'U', 0, 1) }}
                    </div>
                    <div>
                        <p class="text-white text-sm font-medium">{{ $business->createdBy->name }}</p>
                        <p class="text-slate-500 text-xs">Created by</p>
                    </div>
                </div>
            @else
                <p class="text-slate-600 text-sm italic">No owner assigned.</p>
            @endif
        </div>

        <!-- Import Info -->
        @if($business->import_batch_id)
            <div class="glass-card p-6">
                <h3 class="text-white font-semibold mb-4">Import Info</h3>
                <div class="space-y-2">
                    <div class="bg-white/5 rounded-xl p-3">
                        <p class="text-xs text-slate-500">Batch ID</p>
                        <p class="text-white text-sm">#{{ $business->import_batch_id }}</p>
                    </div>
                    @if($business->confidence)
                        <div class="bg-white/5 rounded-xl p-3">
                            <p class="text-xs text-slate-500">Confidence</p>
                            <p class="text-white text-sm">{{ $business->confidence }}%</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Meta Info -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Meta</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">ID</span>
                    <span class="text-white">#{{ $business->id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Slug</span>
                    <span class="text-white">{{ $business->slug ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Created</span>
                    <span class="text-white">{{ $business->created_at->format('M d, Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Updated</span>
                    <span class="text-white">{{ $business->updated_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
