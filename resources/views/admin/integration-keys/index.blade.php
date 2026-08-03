@extends('layouts.admin')

@section('title', 'Integration API Keys')
@section('header', 'Integration API Keys')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    @if(session('new_key'))
    <div class="glass-card p-6 rounded-lg border border-amber-500/30">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center shrink-0 mt-0.5">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-amber-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-amber-400 font-semibold text-sm">API Key Generated</h3>
                <p class="text-slate-400 text-xs mt-1">Copy this key now. You won't be able to see it again.</p>
                <div class="mt-3 flex gap-2">
                    <input type="text" value="{{ session('new_key') }}" readonly
                        class="input-dark text-xs font-mono flex-1 select-all" id="newKeyInput">
                    <button onclick="copyKey()" class="btn-ghost text-xs px-3 py-2">Copy</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    function copyKey() {
        const input = document.getElementById('newKeyInput');
        input.select();
        navigator.clipboard?.writeText(input.value);
        input.classList.add('border-green-500/50');
        setTimeout(() => input.classList.remove('border-green-500/50'), 2000);
    }
    </script>
    @endif

    <div class="glass-card p-6 rounded-lg">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-white font-semibold">API Keys</h3>
                <p class="text-slate-400 text-xs mt-1">Keys used by external services (Eiho One, AI Agent, ERP systems) to connect to your data.</p>
            </div>
            <button onclick="document.getElementById('createForm').classList.toggle('hidden')"
                class="btn-primary text-sm px-4 py-2">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4 inline mr-1.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Key
            </button>
        </div>

        <form id="createForm" method="POST" action="{{ route('admin.integration-keys.generate') }}" class="hidden mb-6 p-4 bg-white/5 rounded-lg space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Key Name</label>
                    <input type="text" name="name" required placeholder="e.g. Eiho One Directory" class="input-dark text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Tenant</label>
                    <select name="tenant_type" class="input-dark text-sm w-full">
                        <option value="hola">Eiho One</option>
                        <option value="ai_agent">AI Agent</option>
                        <option value="restaurant">Restaurant ERP</option>
                        <option value="school">School ERP</option>
                        <option value="shop">Shopping</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Scopes</label>
                    <select name="scopes" class="input-dark text-sm w-full">
                        <option value="*">Full Access</option>
                        <option value="businesses:read">Businesses (read only)</option>
                        <option value="businesses:read,leads:read,leads:write">Businesses + Leads</option>
                        <option value="products:read,products:write,orders:read,orders:write">Products + Orders</option>
                        <option value="bookings:read,bookings:write,services:read,services:write">Bookings + Services</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="this.closest('form').classList.add('hidden')" class="btn-ghost text-xs px-3 py-1.5">Cancel</button>
                <button type="submit" class="btn-primary text-xs px-4 py-1.5">Generate Key</button>
            </div>
        </form>

        @if($keys->count())
        <div class="overflow-x-auto">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th class="text-left">Name</th>
                        <th class="text-left">Prefix</th>
                        <th class="text-left">Tenant</th>
                        <th class="text-left">Scopes</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Last Used</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($keys as $key)
                    <tr>
                        <td class="text-sm font-medium text-white">{{ $key->name }}</td>
                        <td class="text-sm font-mono text-slate-400">{{ $key->key_prefix }}...</td>
                        <td class="text-sm text-slate-400">{{ $key->tenant_type ?? '—' }}</td>
                        <td class="text-sm">
                            @if(in_array('*', $key->scopes ?? []))
                                <span class="badge badge-green">All</span>
                            @else
                                <span class="text-xs text-slate-400">{{ implode(', ', array_slice($key->scopes ?? [], 0, 2)) }}{{ count($key->scopes ?? []) > 2 ? '...' : '' }}</span>
                            @endif
                        </td>
                        <td>
                            @if($key->is_revoked)
                                <span class="badge badge-red">Revoked</span>
                            @elseif($key->expires_at && $key->expires_at->isPast())
                                <span class="badge badge-yellow">Expired</span>
                            @else
                                <span class="badge badge-green">Active</span>
                            @endif
                        </td>
                        <td class="text-sm text-slate-400">{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never' }}</td>
                        <td class="text-right">
                            @if(!$key->is_revoked)
                            <form method="POST" action="{{ route('admin.integration-keys.revoke', $key->id) }}" class="inline"
                                onsubmit="return confirm('Revoke this key? Any service using it will lose access.')">
                                @csrf
                                <button type="submit" class="text-red-400 hover:text-red-300 text-xs font-medium">Revoke</button>
                            </form>
                            @else
                            <span class="text-slate-600 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $keys->links() }}</div>
        @else
        <div class="text-center py-12">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-12 h-12 text-slate-600 mx-auto mb-3"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            <p class="text-slate-500 text-sm">No API keys yet.</p>
            <p class="text-slate-600 text-xs mt-1">Click "New Key" to generate your first integration key.</p>
        </div>
        @endif
    </div>
</div>
@endsection
