@extends('layouts.admin')

@section('title', 'Message Center')
@section('header', 'Message Center')

@section('content')
<div class="mb-6">
    <h3 class="text-white font-semibold text-lg">Message Center</h3>
    <p class="text-slate-500 text-sm mt-1">Notify imported businesses that they are on Eiho One and can claim their listing. Configure WhatsApp/SMS/Telegram below.</p>
</div>

@if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-3 rounded-lg mb-6">{{ $errors->first() }}</div>
@endif

<!-- Gateway status -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-white">{{ $readyCount }}</div>
        <div class="text-xs text-slate-400 mt-1">Businesses with phone — ready to notify</div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-amber-400">{{ $noContactCount }}</div>
        <div class="text-xs text-slate-400 mt-1">Businesses with no phone (cannot reach)</div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold {{ count($channels) > 0 ? 'text-emerald-400' : 'text-red-400' }}">{{ count($channels) }}</div>
        <div class="text-xs text-slate-400 mt-1">Gateways configured</div>
        <div class="mt-2 flex flex-wrap gap-1">
            @forelse($channels as $name => $label)
                <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-500/20 text-emerald-300">{{ $label }}</span>
            @empty
                <span class="px-2 py-0.5 text-xs rounded-full bg-red-500/20 text-red-300">None — configure below</span>
            @endforelse
        </div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold {{ $autoPilot['enabled'] ? 'text-emerald-400' : 'text-slate-400' }}">{{ $autoPilot['enabled'] ? 'ON' : 'OFF' }}</div>
        <div class="text-xs text-slate-400 mt-1">Auto-pilot (claim notifications)</div>
        <div class="mt-2">
            @if($autoPilot['enabled'])
                <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-500/20 text-emerald-300">Fires daily 10am</span>
            @else
                <span class="px-2 py-0.5 text-xs rounded-full bg-slate-600/20 text-slate-300">Nothing is sent</span>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Template -->
    <div class="glass-card p-5 rounded-xl">
        <h4 class="text-white font-semibold mb-3">Claim invitation template</h4>
        <p class="text-xs text-slate-500 mb-3">Placeholders: <code>{business_name}</code> <code>{claim_url}</code> <code>{site_name}</code> <code>{district}</code> <code>{support_phone}</code></p>
        <form method="POST" action="{{ route('admin.message-center.template') }}">
            @csrf
            <textarea name="template" rows="5" class="input-dark w-full font-mono text-sm">{{ $template }}</textarea>
            <div class="mt-2 flex justify-end">
                <button class="btn-primary text-sm">Save template</button>
            </div>
        </form>
    </div>

    <!-- Send -->
    <div class="glass-card p-5 rounded-xl">
        <h4 class="text-white font-semibold mb-3">Send claim invitations</h4>
        <p class="text-xs text-slate-500 mb-3">Sends to unclaimed imported businesses that have a phone number. Every attempt is logged.</p>
        <form method="POST" action="{{ route('admin.message-center.send') }}">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Channel</label>
                    <select name="channel" class="input-dark w-full">
                        <option value="whatsapp_meta">WhatsApp (Meta Cloud API)</option>
                        <option value="whatsapp">WhatsApp (CallMeBot)</option>
                        <option value="sms">SMS (MSG91)</option>
                        <option value="telegram">Telegram</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Limit</label>
                    <input type="number" name="limit" value="50" min="1" max="500" class="input-dark w-full">
                </div>
            </div>
            <div class="mt-3">
                <button class="btn-primary text-sm">Send batch</button>
            </div>
            @if(count($channels) === 0)
                <p class="text-xs text-amber-400 mt-3">⚠️ No gateway configured yet. Add a key in Settings → API, then this button will work.</p>
            @endif
        </form>
    </div>
</div>

<!-- Auto-pilot -->
<div class="glass-card p-5 rounded-xl mt-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h4 class="text-white font-semibold">Auto-pilot</h4>
            <p class="text-xs text-slate-500 mt-1">Automatically invite unclaimed businesses, then send follow-up reminders to those still unclaimed. Runs daily at 10am — only when ON.</p>
        </div>
        <span class="px-3 py-1 text-xs rounded-full {{ $autoPilot['enabled'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-600/20 text-slate-300' }}">
            {{ $autoPilot['enabled'] ? 'ON — firing daily' : 'OFF — nothing sent' }}
        </span>
    </div>
    <form method="POST" action="{{ route('admin.message-center.autopilot') }}">
        @csrf
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <div>
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="enabled" value="1" {{ $autoPilot['enabled'] ? 'checked' : '' }}> Enable auto-pilot</label>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Channel</label>
                <select name="channel" class="input-dark">
                    <option value="whatsapp_meta" {{ $autoPilot['channel'] === 'whatsapp_meta' ? 'selected' : '' }}>WhatsApp (Meta)</option>
                    <option value="whatsapp" {{ $autoPilot['channel'] === 'whatsapp' ? 'selected' : '' }}>WhatsApp (CallMeBot)</option>
                    <option value="sms" {{ $autoPilot['channel'] === 'sms' ? 'selected' : '' }}>SMS (MSG91)</option>
                    <option value="telegram" {{ $autoPilot['channel'] === 'telegram' ? 'selected' : '' }}>Telegram</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Batch per run</label>
                <input type="number" name="batch" value="{{ $autoPilot['batch'] }}" min="1" max="500" class="input-dark">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">First invite after (days)</label>
                <input type="number" name="first_after_days" value="{{ $autoPilot['first_after_days'] }}" min="0" max="30" class="input-dark">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Reminder after (days)</label>
                <input type="number" name="reminder_after_days" value="{{ $autoPilot['reminder_after_days'] }}" min="1" max="60" class="input-dark">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Max reminders</label>
                <input type="number" name="max_reminders" value="{{ $autoPilot['max_reminders'] }}" min="1" max="10" class="input-dark">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs text-slate-400 mb-1">Reminder message</label>
                <textarea name="reminder_template" rows="3" class="input-dark w-full font-mono text-sm">{{ $reminderTemplate }}</textarea>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn-primary text-sm">Save auto-pilot</button>
        </div>
    </form>
</div>

<!-- Gateway setup help -->
<div class="glass-card p-5 rounded-xl mt-6">
    <h4 class="text-white font-semibold mb-3">Gateway setup</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div class="bg-white/5 rounded-lg p-4">
            <h5 class="text-white font-medium mb-1">WhatsApp (CallMeBot)</h5>
            <p class="text-xs text-slate-400">Free, up to ~1 msg/day per number. Get a key at <span class="text-blue-400">callmebot.com</span> → WhatsApp → API.</p>
            <p class="text-xs text-slate-500 mt-2">Set <code>callmebot_api_key</code> in Settings → API.</p>
        </div>
        <div class="bg-white/5 rounded-lg p-4">
            <h5 class="text-white font-medium mb-1">SMS (MSG91)</h5>
            <p class="text-xs text-slate-400">Reliable, costs per SMS. Get auth key + flow template id.</p>
            <p class="text-xs text-slate-500 mt-2">Set <code>sms_msg91_auth_key</code> + <code>sms_msg91_template_id</code> in Settings → API.</p>
        </div>
        <div class="bg-white/5 rounded-lg p-4">
            <h5 class="text-white font-medium mb-1">Telegram</h5>
            <p class="text-xs text-slate-400">Send notifications to a single Telegram chat (your team).</p>
            <p class="text-xs text-slate-500 mt-2">Set <code>telegram_bot_token</code> + <code>telegram_chat_id</code> in Settings.</p>
        </div>
    </div>
    <div class="mt-4">
        <a href="{{ route('admin.settings') }}" class="btn-ghost text-sm">Open Settings → API & Notifications</a>
        <a href="{{ route('admin.message-center.logs') }}" class="btn-ghost text-sm">View notification log</a>
    </div>
</div>

<!-- Recent log -->
@if($recentLogs->isNotEmpty())
<div class="glass-card rounded-lg overflow-hidden mt-6">
    <div class="px-5 py-4 border-b border-white/10 flex justify-between items-center">
        <h4 class="text-white font-semibold">Recent claim invitations</h4>
        <a href="{{ route('admin.message-center.logs') }}" class="text-blue-400 text-xs">View all</a>
    </div>
    <table class="data-table">
        <thead>
            <tr><th>Business</th><th>Channel</th><th>Status</th><th>Sent</th></tr>
        </thead>
        <tbody>
            @foreach($recentLogs as $log)
                <tr>
                    <td class="text-sm">{{ $log->business?->name ?? '—' }}</td>
                    <td class="text-sm">{{ $log->channel }}</td>
                    <td>
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $log->status === 'sent' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' }}">{{ $log->status }}</span>
                    </td>
                    <td class="text-sm text-slate-500">{{ $log->sent_at?->diffForHumans() ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
