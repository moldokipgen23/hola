@extends('vendor.layouts.dashboard')

@section('title', 'Setup Your Business')
@section('header', 'Setup Your Business')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="text-center mb-8">
        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-purple-500 to-blue-600 flex items-center justify-center">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <h2 class="text-2xl font-bold text-white mb-2">What type of business do you run?</h2>
        <p class="text-slate-400">Pick one. You can always change this later in Business Features.</p>
    </div>

    <form method="POST" action="{{ route('vendor.businesses.setup.post', $business->id) }}">
        @csrf

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded mb-6">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 mb-8">
            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group {{ old('business_type') == 'restaurant' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="business_type" value="restaurant" class="hidden" {{ old('business_type') == 'restaurant' ? 'checked' : '' }}>
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-orange-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="text-2xl">🍽️</span>
                    </div>
                    <p class="font-semibold text-white">Restaurant / Food</p>
                    <p class="text-xs text-slate-500 mt-1">Menu, orders, delivery</p>
                </div>
            </label>

            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group {{ old('business_type') == 'retail' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="business_type" value="retail" class="hidden" {{ old('business_type') == 'retail' ? 'checked' : '' }}>
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-blue-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="text-2xl">🛍️</span>
                    </div>
                    <p class="font-semibold text-white">Retail / Shop</p>
                    <p class="text-xs text-slate-500 mt-1">Products, orders, inventory</p>
                </div>
            </label>

            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group {{ old('business_type') == 'salon' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="business_type" value="salon" class="hidden" {{ old('business_type') == 'salon' ? 'checked' : '' }}>
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-pink-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="text-2xl">💇</span>
                    </div>
                    <p class="font-semibold text-white">Salon / Beauty</p>
                    <p class="text-xs text-slate-500 mt-1">Appointments, services</p>
                </div>
            </label>

            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group {{ old('business_type') == 'hotel' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="business_type" value="hotel" class="hidden" {{ old('business_type') == 'hotel' ? 'checked' : '' }}>
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-teal-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="text-2xl">🏨</span>
                    </div>
                    <p class="font-semibold text-white">Hotel / Stay</p>
                    <p class="text-xs text-slate-500 mt-1">Rooms, bookings</p>
                </div>
            </label>

            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group {{ old('business_type') == 'turf' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="business_type" value="turf" class="hidden" {{ old('business_type') == 'turf' ? 'checked' : '' }}>
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-green-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="text-2xl">⚽</span>
                    </div>
                    <p class="font-semibold text-white">Turf / Sports</p>
                    <p class="text-xs text-slate-500 mt-1">Slots, courts, bookings</p>
                </div>
            </label>

            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group {{ old('business_type') == 'taxi' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="business_type" value="taxi" class="hidden" {{ old('business_type') == 'taxi' ? 'checked' : '' }}>
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-yellow-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="text-2xl">🚕</span>
                    </div>
                    <p class="font-semibold text-white">Taxi / Transport</p>
                    <p class="text-xs text-slate-500 mt-1">Vehicles, routes, trips</p>
                </div>
            </label>

            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group {{ old('business_type') == 'event' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="business_type" value="event" class="hidden" {{ old('business_type') == 'event' ? 'checked' : '' }}>
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-red-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="text-2xl">🎤</span>
                    </div>
                    <p class="font-semibold text-white">Event Venue</p>
                    <p class="text-xs text-slate-500 mt-1">Seats, slots, bookings</p>
                </div>
            </label>

            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group {{ old('business_type') == 'other' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="business_type" value="other" class="hidden" {{ old('business_type') == 'other' ? 'checked' : '' }}>
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-slate-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="text-2xl">📋</span>
                    </div>
                    <p class="font-semibold text-white">Other / General</p>
                    <p class="text-xs text-slate-500 mt-1">Basic listing, contact info</p>
                </div>
            </label>
        </div>

        <div class="flex gap-3 justify-center">
            <button type="submit" class="btn-primary px-8">Continue</button>
            <a href="{{ route('vendor.dashboard') }}" class="btn-ghost">Skip for now</a>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('label[class*="glass-card"]').forEach(label => {
    label.addEventListener('click', function() {
        document.querySelectorAll('label[class*="glass-card"]').forEach(l => {
            l.classList.remove('border-purple-500', 'bg-purple-500/10');
        });
        this.classList.add('border-purple-500', 'bg-purple-500/10');
    });
});
</script>
@endsection
