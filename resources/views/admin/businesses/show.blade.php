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
    <!-- Main Info -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Basic Info -->
        <div class="glass-card p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold text-white">{{ $business->name }}</h2>
                    <p class="text-slate-400 text-sm mt-1">{{ $business->address }}</p>
                </div>
                <div class="flex gap-2">
                    @if($business->is_active)
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="badge badge-red">Inactive</span>
                    @endif
                    @if($business->is_featured)
                        <span class="badge badge-yellow">Featured</span>
                    @endif
                </div>
            </div>

            @if($business->description)
                <p class="text-slate-300 text-sm leading-relaxed">{{ $business->description }}</p>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500">Category</p>
                    <p class="text-white font-medium">{{ $business->category->name ?? '-' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500">Subcategory</p>
                    <p class="text-white font-medium">{{ $business->subcategory->name ?? '-' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500">Locality</p>
                    <p class="text-white font-medium">{{ $business->locality ?? '-' }}</p>
                </div>
                <div class="bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500">District</p>
                    <p class="text-white font-medium">{{ $business->district ?? '-' }}</p>
                </div>
            </div>
        </div>

        <!-- Contact & Links -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Contact Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if($business->phone)
                    <div class="flex items-center gap-3 bg-white/5 rounded-xl p-3">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-green-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <div>
                            <p class="text-xs text-slate-500">Phone</p>
                            <p class="text-white text-sm">{{ $business->phone }}</p>
                        </div>
                    </div>
                @endif
                @if($business->email)
                    <div class="flex items-center gap-3 bg-white/5 rounded-xl p-3">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-blue-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <div>
                            <p class="text-xs text-slate-500">Email</p>
                            <p class="text-white text-sm">{{ $business->email }}</p>
                        </div>
                    </div>
                @endif
                @if($business->website)
                    <div class="flex items-center gap-3 bg-white/5 rounded-xl p-3">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-purple-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                        <div>
                            <p class="text-xs text-slate-500">Website</p>
                            <a href="{{ $business->website }}" target="_blank" class="text-white text-sm hover:text-blue-400">{{ $business->website }}</a>
                        </div>
                    </div>
                @endif
                @if($business->whatsapp)
                    <div class="flex items-center gap-3 bg-white/5 rounded-xl p-3">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-emerald-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <div>
                            <p class="text-xs text-slate-500">WhatsApp</p>
                            <p class="text-white text-sm">{{ $business->whatsapp }}</p>
                        </div>
                    </div>
                @endif
            </div>

            @if($business->latitude && $business->longitude)
                <div class="mt-4 bg-white/5 rounded-xl p-3">
                    <p class="text-xs text-slate-500 mb-1">Coordinates</p>
                    <p class="text-white text-sm">{{ $business->latitude }}, {{ $business->longitude }}</p>
                </div>
            @endif
        </div>

        <!-- Products -->
        @if($business->products->count())
            <div class="glass-card p-6">
                <h3 class="text-white font-semibold mb-4">Products ({{ $business->products->count() }})</h3>
                <div class="space-y-2">
                    @foreach($business->products as $product)
                        <div class="flex justify-between items-center bg-white/5 rounded-xl p-3">
                            <div>
                                <p class="text-white text-sm font-medium">{{ $product->name }}</p>
                                @if($product->description)
                                    <p class="text-slate-500 text-xs">{{ Str::limit($product->description, 80) }}</p>
                                @endif
                            </div>
                            <div class="text-right">
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
            </div>
        @endif
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Stats -->
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
                    <span class="text-slate-400 text-sm">Average Rating</span>
                    <span class="text-yellow-400 font-semibold">{{ number_format($business->average_rating ?? 0, 1) }} / 5</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 text-sm">Total Reviews</span>
                    <span class="text-white font-semibold">{{ $business->review_count ?? 0 }}</span>
                </div>
            </div>
        </div>

        <!-- Owner -->
        @if($business->user)
            <div class="glass-card p-6">
                <h3 class="text-white font-semibold mb-4">Owner</h3>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold">
                        {{ substr($business->user->name ?? 'U', 0, 1) }}
                    </div>
                    <div>
                        <p class="text-white text-sm font-medium">{{ $business->user->name }}</p>
                        <p class="text-slate-500 text-xs">{{ $business->user->email }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Actions -->
        <div class="glass-card p-6">
            <h3 class="text-white font-semibold mb-4">Actions</h3>
            <div class="space-y-2">
                <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn-primary w-full text-center block">Edit Business</a>
                @if($business->latitude && $business->longitude)
                    <a href="https://www.google.com/maps?q={{ $business->latitude }},{{ $business->longitude }}" target="_blank" class="btn-ghost w-full text-center block">Open in Maps</a>
                @endif
            </div>
        </div>

        <!-- Reviews -->
        @if($business->reviews->count())
            <div class="glass-card p-6">
                <h3 class="text-white font-semibold mb-4">Reviews ({{ $business->reviews->count() }})</h3>
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    @foreach($business->reviews->take(10) as $review)
                        <div class="bg-white/5 rounded-xl p-3">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-white text-sm font-medium">{{ $review->user->name ?? 'Anonymous' }}</span>
                                <span class="text-yellow-400 text-xs">{{ $review->rating }}/5</span>
                            </div>
                            @if($review->comment)
                                <p class="text-slate-400 text-xs">{{ $review->comment }}</p>
                            @endif
                            <p class="text-slate-600 text-xs mt-1">{{ $review->created_at->diffForHumans() }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
