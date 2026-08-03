@extends('vendor.layouts.dashboard')

@section('title', 'Set Up Your Business')
@section('header', 'Set Up Your Business')

@section('content')
<div class="max-w-2xl mx-auto">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded mb-6">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <!-- Progress -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-2">
            @foreach([1,2,3,4,5,6,7,8,9] as $step)
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all {{ $currentStep >= $step ? 'bg-purple-500 text-white' : 'bg-white/5 text-slate-500' }}">
                    @if($currentStep > $step)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @else
                        {{ $step }}
                    @endif
                </div>
            @endforeach
        </div>
        <div class="w-full bg-white/5 rounded-full h-1.5">
            <div class="bg-gradient-to-r from-purple-500 to-blue-500 h-1.5 rounded-full transition-all" style="width: {{ ($currentStep / 9) * 100 }}%"></div>
        </div>
        <p class="text-slate-500 text-xs mt-2">Step {{ $currentStep }} of 9 — {{ $stepLabels[$currentStep - 1] }}</p>
    </div>

    <form method="POST" action="{{ route('vendor.onboarding.store', ['id' => $business->id, 'step' => $currentStep]) }}">
        @csrf

        <!-- Step 1: Find or Create -->
        @if($currentStep === 1)
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-xl font-bold text-white mb-2">Is your business already listed on HOLA?</h3>
            <p class="text-slate-400 text-sm mb-6">We may have already imported your business from Google or other sources.</p>
            <div class="space-y-3">
                <label class="glass-card p-4 rounded-lg cursor-pointer hover:border-purple-500/50 flex items-center gap-4 {{ old('has_listing') == 'yes' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                    <input type="radio" name="has_listing" value="yes" class="hidden" {{ old('has_listing') == 'yes' ? 'checked' : '' }}>
                    <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-white font-medium">Yes, search for my business</p>
                        <p class="text-slate-500 text-sm">I'll claim an existing listing</p>
                    </div>
                </label>
                <label class="glass-card p-4 rounded-lg cursor-pointer hover:border-purple-500/50 flex items-center gap-4 {{ old('has_listing') == 'no' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                    <input type="radio" name="has_listing" value="no" class="hidden" {{ old('has_listing') == 'no' ? 'checked' : '' }}>
                    <div class="w-10 h-10 rounded-xl bg-green-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </div>
                    <div>
                        <p class="text-white font-medium">No, add a new business</p>
                        <p class="text-slate-500 text-sm">I'm listing my business for the first time</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Step 2: Business Identity -->
        @elseif($currentStep === 2)
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-xl font-bold text-white mb-2">What kind of business do you run?</h3>
            <p class="text-slate-400 text-sm mb-6">This helps us set up the right features for you.</p>
            <div class="grid grid-cols-2 gap-3">
                @foreach($businessTypes as $type)
                <label class="glass-card p-4 rounded-lg cursor-pointer hover:border-purple-500/50 transition-all text-center {{ old('business_type') == $type['key'] ? 'border-purple-500 bg-purple-500/10' : '' }}">
                    <input type="radio" name="business_type" value="{{ $type['key'] }}" class="hidden" {{ old('business_type') == $type['key'] ? 'checked' : '' }}>
                    <div class="text-2xl mb-2">{{ $type['icon'] }}</div>
                    <p class="text-white font-medium text-sm">{{ $type['label'] }}</p>
                    <p class="text-slate-500 text-xs mt-1">{{ $type['desc'] }}</p>
                </label>
                @endforeach
            </div>
        </div>

        <!-- Step 3: Specific Category -->
        @elseif($currentStep === 3)
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-xl font-bold text-white mb-2">What specifically do you sell or offer?</h3>
            <p class="text-slate-400 text-sm mb-6">Pick the closest match. You can always change this later.</p>
            <div class="grid grid-cols-2 gap-3">
                @foreach($categories as $cat)
                <label class="glass-card p-4 rounded-lg cursor-pointer hover:border-purple-500/50 transition-all text-center {{ old('category_id') == $cat->id ? 'border-purple-500 bg-purple-500/10' : '' }}">
                    <input type="radio" name="category_id" value="{{ $cat->id }}" class="hidden" {{ old('category_id') == $cat->id ? 'checked' : '' }}>
                    <p class="text-white font-medium text-sm">{{ $cat->name }}</p>
                    @if($cat->module_type)
                        <p class="text-slate-500 text-xs mt-1">{{ ucfirst($cat->module_type) }}</p>
                    @endif
                </label>
                @endforeach
            </div>
        </div>

        <!-- Step 4: Customer Actions -->
        @elseif($currentStep === 4)
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-xl font-bold text-white mb-2">What should customers be able to do?</h3>
            <p class="text-slate-400 text-sm mb-6">Select all that apply. We'll set up the right features automatically.</p>
            <div class="space-y-3">
                @foreach($capabilities as $cap)
                <label class="glass-card p-4 rounded-lg cursor-pointer hover:border-purple-500/50 flex items-center gap-4 {{ in_array($cap['key'], old('capabilities', [])) ? 'border-purple-500 bg-purple-500/10' : '' }}">
                    <input type="checkbox" name="capabilities[]" value="{{ $cap['key'] }}" class="hidden" {{ in_array($cap['key'], old('capabilities', [])) ? 'checked' : '' }}>
                    <div class="w-10 h-10 rounded-xl bg-{{ $cap['color'] }}-500/20 flex items-center justify-center shrink-0">
                        <span class="text-lg">{{ $cap['icon'] }}</span>
                    </div>
                    <div>
                        <p class="text-white font-medium">{{ $cap['label'] }}</p>
                        <p class="text-slate-500 text-sm">{{ $cap['desc'] }}</p>
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        <!-- Step 5: Confirmation Method -->
        @elseif($currentStep === 5)
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-xl font-bold text-white mb-2">How should customer requests work?</h3>
            <p class="text-slate-400 text-sm mb-6">This controls how orders and bookings are handled.</p>
            <div class="space-y-3">
                @foreach($confirmationOptions as $opt)
                <label class="glass-card p-4 rounded-lg cursor-pointer hover:border-purple-500/50 flex items-center gap-4 {{ old('confirmation_mode') == $opt['key'] ? 'border-purple-500 bg-purple-500/10' : '' }}">
                    <input type="radio" name="confirmation_mode" value="{{ $opt['key'] }}" class="hidden" {{ old('confirmation_mode') == $opt['key'] ? 'checked' : '' }}>
                    <div class="w-10 h-10 rounded-xl bg-{{ $opt['color'] }}-500/20 flex items-center justify-center shrink-0">
                        <span class="text-lg">{{ $opt['icon'] }}</span>
                    </div>
                    <div>
                        <p class="text-white font-medium">{{ $opt['label'] }}</p>
                        <p class="text-slate-500 text-sm">{{ $opt['desc'] }}</p>
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        <!-- Step 6: Fulfilment -->
        @elseif($currentStep === 6)
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-xl font-bold text-white mb-2">How do customers get their orders?</h3>
            <p class="text-slate-400 text-sm mb-6">Select all that apply.</p>
            <div class="space-y-3">
                @foreach($fulfilmentOptions as $opt)
                <label class="glass-card p-4 rounded-lg cursor-pointer hover:border-purple-500/50 flex items-center gap-4 {{ in_array($opt['key'], old('fulfilment', [])) ? 'border-purple-500 bg-purple-500/10' : '' }}">
                    <input type="checkbox" name="fulfilment[]" value="{{ $opt['key'] }}" class="hidden" {{ in_array($opt['key'], old('fulfilment', [])) ? 'checked' : '' }}>
                    <div class="w-10 h-10 rounded-xl bg-{{ $opt['color'] }}-500/20 flex items-center justify-center shrink-0">
                        <span class="text-lg">{{ $opt['icon'] }}</span>
                    </div>
                    <div>
                        <p class="text-white font-medium">{{ $opt['label'] }}</p>
                        <p class="text-slate-500 text-sm">{{ $opt['desc'] }}</p>
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        <!-- Step 7: Business Profile -->
        @elseif($currentStep === 7)
        <div class="glass-card p-6 rounded-xl space-y-4">
            <h3 class="text-xl font-bold text-white mb-2">Tell us about your business</h3>
            <p class="text-slate-400 text-sm mb-4">This is what customers will see.</p>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Business Name *</label>
                <input type="text" name="name" value="{{ old('name', $business->name) }}" required class="input-dark" placeholder="e.g. Tasty Bites">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Phone *</label>
                <input type="text" name="phone" value="{{ old('phone', $business->phone) }}" required class="input-dark" placeholder="+91 XXXXX XXXXX">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">WhatsApp</label>
                <input type="text" name="whatsapp" value="{{ old('whatsapp', $business->whatsapp) }}" class="input-dark" placeholder="+91 XXXXX XXXXX">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Address *</label>
                <input type="text" name="address" value="{{ old('address', $business->address) }}" required class="input-dark" placeholder="Full address">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Short Description</label>
                <textarea name="description" rows="2" class="input-dark" placeholder="What makes your business special?">{{ old('description', $business->description) }}</textarea>
            </div>
        </div>

        <!-- Step 8: Operating Hours -->
        @elseif($currentStep === 8)
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-xl font-bold text-white mb-2">When are you open?</h3>
            <p class="text-slate-400 text-sm mb-6">Set your weekly schedule.</p>

            <div class="space-y-3">
                @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                @php
                    $hours = old("hours.{$day}", $existingHours[$day] ?? null);
                @endphp
                <div class="flex items-center gap-4">
                    <span class="text-white font-medium w-24 capitalize text-sm">{{ $day }}</span>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="hours[{{ $day }}][open]" value="1" class="rounded" {{ $hours ? 'checked' : '' }} onchange="this.closest('.flex').querySelectorAll('input[type=time]').forEach(t => t.disabled = !this.checked)">
                        <span class="text-slate-400 text-sm">Open</span>
                    </label>
                    <input type="time" name="hours[{{ $day }}][open_time]" value="{{ $hours['open_time'] ?? '09:00' }}" class="input-dark w-32" {{ !$hours ? 'disabled' : '' }}>
                    <span class="text-slate-500">to</span>
                    <input type="time" name="hours[{{ $day }}][close_time]" value="{{ $hours['close_time'] ?? '18:00' }}" class="input-dark w-32" {{ !$hours ? 'disabled' : '' }}>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Step 9: Review -->
        @elseif($currentStep === 9)
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-xl font-bold text-white mb-2">Review your setup</h3>
            <p class="text-slate-400 text-sm mb-6">Everything looks good? Hit publish to go live.</p>

            <div class="space-y-4">
                <div class="p-4 bg-white/5 rounded-lg">
                    <h4 class="text-white font-medium mb-2">Business</h4>
                    <p class="text-slate-400 text-sm">{{ $business->name }}</p>
                    <p class="text-slate-500 text-xs">{{ $business->address }}</p>
                </div>

                <div class="p-4 bg-white/5 rounded-lg">
                    <h4 class="text-white font-medium mb-2">Category</h4>
                    <p class="text-slate-400 text-sm">{{ $selectedCategory?->name ?? 'Not selected' }}</p>
                </div>

                <div class="p-4 bg-white/5 rounded-lg">
                    <h4 class="text-white font-medium mb-2">Customer Actions</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($selectedCapabilities as $cap)
                            <span class="badge badge-blue">{{ $cap }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="p-4 bg-white/5 rounded-lg">
                    <h4 class="text-white font-medium mb-2">Confirmation</h4>
                    <p class="text-slate-400 text-sm">{{ ucfirst($selectedConfirmation) }}</p>
                </div>

                <div class="p-4 bg-white/5 rounded-lg">
                    <h4 class="text-white font-medium mb-2">Customers Can:</h4>
                    <ul class="text-slate-400 text-sm space-y-1">
                        <li>• Find your business</li>
                        @foreach($selectedCapabilities as $cap)
                            <li>• {{ $capDescriptions[$cap] ?? $cap }}</li>
                        @endforeach
                        <li>• Pay you directly</li>
                    </ul>
                </div>
            </div>
        </div>
        @endif

        <div class="flex gap-3 mt-6">
            @if($currentStep > 1)
                <a href="{{ route('vendor.onboarding.step', ['id' => $business->id, 'step' => $currentStep - 1]) }}" class="btn-ghost">Back</a>
            @endif
            <button type="submit" class="btn-primary flex-1">
                @if($currentStep === 9)
                    Publish Business
                @else
                    Continue
                @endif
            </button>
            @if($currentStep < 9)
                <a href="{{ route('vendor.dashboard') }}" class="btn-ghost">Skip</a>
            @endif
        </div>
    </form>
</div>

<script>
document.querySelectorAll('input[type=radio], input[type=checkbox]').forEach(input => {
    input.addEventListener('change', function() {
        const label = this.closest('label');
        if (!label) return;
        if (this.type === 'radio') {
            label.closest('.grid, .space-y-3')?.querySelectorAll('label').forEach(l => {
                l.classList.remove('border-purple-500', 'bg-purple-500/10');
            });
        }
        if (this.checked) {
            label.classList.add('border-purple-500', 'bg-purple-500/10');
        } else {
            label.classList.remove('border-purple-500', 'bg-purple-500/10');
        }
    });
});
</script>
@endsection
