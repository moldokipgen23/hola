@extends('vendor.layouts.dashboard')

@section('title', 'Settings')
@section('header', 'Settings')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded">{{ session('success') }}</div>
    @endif

    @if($currentBiz)
    <!-- Business Health -->
    @php
        $modules = $currentBiz->effectiveModules();
        $readiness = $currentBiz->moduleReadiness();
        $hasPhoto = $currentBiz->photos && count($currentBiz->photos) > 0;
        $hasHours = $currentBiz->working_hours && count((array)$currentBiz->working_hours) > 0;
        $hasDesc = $currentBiz->description && strlen($currentBiz->description) > 20;
        $hasWhatsApp = !empty($currentBiz->whatsapp);
        $checks = [
            ['label' => 'Business name', 'done' => !empty($currentBiz->name)],
            ['label' => 'Description', 'done' => $hasDesc],
            ['label' => 'Phone number', 'done' => !empty($currentBiz->phone)],
            ['label' => 'WhatsApp', 'done' => $hasWhatsApp],
            ['label' => 'Working hours', 'done' => $hasHours],
            ['label' => 'Business photos', 'done' => $hasPhoto],
        ];
        foreach ($modules as $mod => $enabled) {
            if ($enabled && isset($readiness[$mod]) && !$readiness[$mod]['ready']) {
                $checks[] = ['label' => $readiness[$mod]['next_step'], 'done' => false];
            }
        }
        $done = collect($checks)->where('done', true)->count();
        $total = count($checks);
        $percent = $total > 0 ? round(($done / $total) * 100) : 0;
    @endphp
    <div class="glass-card p-6 rounded-lg">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-white font-semibold text-lg">Business Health</h3>
            <span class="text-2xl font-bold {{ $percent >= 80 ? 'text-green-400' : ($percent >= 50 ? 'text-amber-400' : 'text-red-400') }}">{{ $percent }}%</span>
        </div>
        <div class="w-full bg-white/5 rounded-full h-2 mb-4">
            <div class="bg-gradient-to-r from-purple-500 to-blue-500 h-2 rounded-full transition-all" style="width: {{ $percent }}%"></div>
        </div>
        <div class="space-y-2">
            @foreach($checks as $check)
            <div class="flex items-center gap-2 text-sm">
                @if($check['done'])
                    <svg class="w-4 h-4 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-slate-400">{{ $check['label'] }}</span>
                @else
                    <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span class="text-red-400">{{ $check['label'] }}</span>
                @endif
            </div>
            @endforeach
        </div>
        <div class="mt-4 pt-4 border-t border-white/5">
            <a href="{{ route('vendor.businesses.setup', $currentBiz->id) }}" class="text-purple-400 text-sm font-medium hover:text-purple-300">Reconfigure business type →</a>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('vendor.settings.update') }}" class="glass-card p-6 rounded-lg space-y-4">
        @csrf @method('PUT')
        <h3 class="text-white font-semibold text-lg mb-4">Profile</h3>

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="input-dark">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Email</label>
            <input type="email" value="{{ $user->email }}" disabled class="input-dark opacity-60">
            <p class="text-xs text-slate-600 mt-1">Email cannot be changed.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="input-dark">
        </div>
        <button type="submit" class="btn-primary">Save Changes</button>
    </form>

    <form method="POST" action="{{ route('vendor.settings.password') }}" class="glass-card p-6 rounded-lg space-y-4">
        @csrf @method('PUT')
        <h3 class="text-white font-semibold text-lg mb-4">Change Password</h3>

        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Current Password</label>
            <input type="password" name="current_password" required class="input-dark">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">New Password</label>
            <input type="password" name="password" required class="input-dark">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Confirm New Password</label>
            <input type="password" name="password_confirmation" required class="input-dark">
        </div>
        <button type="submit" class="btn-primary">Change Password</button>
    </form>
</div>
@endsection
