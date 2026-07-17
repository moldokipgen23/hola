@extends('layouts.admin')

@section('title', 'Coming Soon Leads')
@section('header', 'Area Interest Leads')

@section('content')
<div class="mb-4">
    <p class="text-slate-400 text-sm">People who want delivery in their area. When you enable an area in the Pincodes panel, these leads become potential customers.</p>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-white/10 text-slate-400 text-left">
                <th class="px-4 py-3">Pincode</th>
                <th class="px-4 py-3">Locality</th>
                <th class="px-4 py-3">District</th>
                <th class="px-4 py-3">State</th>
                <th class="px-4 py-3">Phone</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Enable</th>
            </tr>
        </thead>
        <tbody>
            @forelse($interests as $interest)
                <tr class="border-b border-white/5 hover:bg-white/5">
                    <td class="px-4 py-3 font-mono">{{ $interest->pincode }}</td>
                    <td class="px-4 py-3">{{ $interest->locality ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $interest->district ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $interest->state ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $interest->phone ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $interest->email ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $interest->created_at->format('d M Y') }}</td>
                    <td class="px-4 py-3">
                        <form action="{{ route('admin.pincodes.toggle-state') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="state" value="{{ $interest->state ?? $interest->pincode }}">
                            <input type="hidden" name="enable" value="1">
                            <button type="submit" class="text-xs text-emerald-400 hover:text-emerald-300">
                                Enable area
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-slate-500">No leads yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $interests->links() }}
</div>
@endsection
