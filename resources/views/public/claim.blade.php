@extends('layouts.public')

@section('title', 'Claim ' . $business->name . ' | Hola')

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="flex items-center gap-2 text-sm text-slate-400 mb-3">
            <a href="/" class="hover:text-primary-600">Home</a>
            <span>/</span>
            <a href="/business/{{ $business->slug }}" class="hover:text-primary-600">{{ $business->name }}</a>
            <span>/</span>
            <span class="text-slate-600">Claim</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 mb-2">Claim This Business</h1>
        <p class="text-slate-500 text-sm">Verify that you own or manage <strong>{{ $business->name }}</strong> to take control of this listing.</p>
    </div>
</div>

<div class="max-w-2xl mx-auto px-4 py-8">
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <p class="text-red-700 text-sm">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2">
            <form method="POST" action="{{ route('public.claim.send-otp', $business->id) }}" class="bg-white rounded-xl border border-slate-100 p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Your Full Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-100">
                        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-100">
                        @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">WhatsApp Number (for verification)</label>
                        <input type="tel" name="phone" value="{{ old('phone', $business->phone ?? '') }}" required placeholder="+91XXXXXXXXXX"
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-100">
                        <p class="text-xs text-slate-400 mt-1">We'll send a verification code here. Include country code (+91).</p>
                        @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Your Relationship to This Business</label>
                        <select name="relation" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-primary-300">
                            <option value="">Select...</option>
                            <option value="owner" {{ old('relation') == 'owner' ? 'selected' : '' }}>Owner</option>
                            <option value="manager" {{ old('relation') == 'manager' ? 'selected' : '' }}>Manager</option>
                            <option value="employee" {{ old('relation') == 'employee' ? 'selected' : '' }}>Employee</option>
                            <option value="representative" {{ old('relation') == 'representative' ? 'selected' : '' }}>Authorized Representative</option>
                        </select>
                        @error('relation')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Additional Message (optional)</label>
                        <textarea name="message" rows="3" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-100" placeholder="Any additional details...">{{ old('message') }}</textarea>
                    </div>
                </div>
                <button type="submit" class="w-full mt-6 px-4 py-3 rounded-lg bg-primary-500 text-white text-sm font-semibold hover:bg-primary-600 transition-colors">Send Verification Code</button>
            </form>
        </div>

        <div class="space-y-4">
            <div class="bg-slate-50 rounded-xl border border-slate-100 p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-3">How Verification Works</h3>
                <div class="space-y-3 text-xs text-slate-500">
                    <div class="flex gap-2"><span class="text-primary-500 font-bold">1</span> Enter your details and WhatsApp number</div>
                    <div class="flex gap-2"><span class="text-primary-500 font-bold">2</span> We send a 6-digit code via WhatsApp</div>
                    <div class="flex gap-2"><span class="text-primary-500 font-bold">3</span> Enter the code to verify your identity</div>
                    <div class="flex gap-2"><span class="text-primary-500 font-bold">4</span> Claim submitted for admin review</div>
                </div>
            </div>
            <div class="bg-primary-50 rounded-xl border border-primary-100 p-5">
                <p class="text-xs text-primary-700"><strong>Tip:</strong> If WhatsApp doesn't work, we'll send the code to your email instead.</p>
            </div>
            <a href="/business/{{ $business->slug }}" class="block text-center px-4 py-2.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-primary-300 transition">Back to {{ $business->name }}</a>
        </div>
    </div>
</div>
@endsection
