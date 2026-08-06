@extends('layouts.admin')

@php
    $module = $module ?? 'all';
    $businessTypeId = $businessTypeId ?? 0;
    $typeCounts = $typeCounts ?? [];
    $moduleCounts = $moduleCounts ?? [];
    $moduleLabels = ['shopping' => 'Shopping', 'booking' => 'Booking', 'taxi' => 'Taxi'];
    $filterQuery = request()->except(['module', 'business_type', 'page']);

    $statusColors = [
        'pending' => 'badge-yellow',
        'confirmed' => 'badge-blue',
        'preparing' => 'bg-purple-500/20 text-purple-400',
        'ready' => 'bg-cyan-500/20 text-cyan-400',
        'out_for_delivery' => 'bg-indigo-500/20 text-indigo-400',
        'delivered' => 'badge-green',
        'completed' => 'badge-green',
        'started' => 'bg-cyan-500/20 text-cyan-400',
        'cancelled' => 'badge-red',
        'rejected' => 'badge-red',
        'no_show' => 'bg-slate-500/20 text-slate-400',
        'rescheduled' => 'bg-amber-500/20 text-amber-400',
    ];
    $paymentColors = [
        'pending' => 'badge-yellow',
        'unpaid' => 'badge-red',
        'paid' => 'badge-green',
        'failed' => 'badge-red',
        'refunded' => 'bg-orange-500/20 text-orange-400',
        'partial' => 'bg-amber-500/20 text-amber-400',
    ];
@endphp

@section('title', 'Universal Orders')
@section('header', 'Universal Orders')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <h3 class="text-white font-semibold text-lg">Universal Orders</h3>
        <p class="text-slate-500 text-sm mt-1">Every order, booking and trip across all business modules.</p>
    </div>
</div>

<!-- Business module tabs -->
<div class="flex gap-1 mb-4 border-b border-white/10 overflow-x-auto">
    <a href="{{ route('admin.orders.universal', $filterQuery) }}"
        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ $module === 'all' ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
        All ({{ array_sum($moduleCounts) }})
    </a>
    @foreach($moduleLabels as $key => $label)
        <a href="{{ route('admin.orders.universal', array_merge($filterQuery, ['module' => $key])) }}"
            class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ $module === $key ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
            {{ $label }} ({{ $moduleCounts[$key] ?? 0 }})
        </a>
    @endforeach
</div>

@if($module === 'shopping')
<!-- Shopping business type tabs -->
<div class="flex gap-1 mb-4 border-b border-white/10 overflow-x-auto">
    <a href="{{ route('admin.orders.universal', array_merge($filterQuery, ['module' => 'shopping'])) }}"
        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ ! $businessTypeId ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
        All ({{ array_sum($typeCounts) }})
    </a>
    @foreach($businessTypes as $type)
        <a href="{{ route('admin.orders.universal', array_merge($filterQuery, ['module' => 'shopping', 'business_type' => $type->id])) }}"
            class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ $businessTypeId === $type->id ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
            {{ $type->name }} ({{ $typeCounts[$type->id] ?? 0 }})
        </a>
    @endforeach
</div>
@endif

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <input type="hidden" name="module" value="{{ $module }}">
    @if($businessTypeId)
        <input type="hidden" name="business_type" value="{{ $businessTypeId }}">
    @endif
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customer, order #, phone..."
                class="input-dark w-full">
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                @foreach(['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'completed', 'started', 'cancelled', 'rejected', 'rescheduled', 'no_show'] as $value)
                    <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="payment_status" class="input-dark w-full">
                <option value="">All Payment</option>
                @foreach(['pending', 'unpaid', 'paid', 'failed', 'refunded', 'partial'] as $value)
                    <option value="{{ $value }}" {{ request('payment_status') == $value ? 'selected' : '' }}>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2 items-end">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('admin.orders.universal', ['module' => $module]) }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="glass-card rounded-xl overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Reference</th>
                <th>Customer</th>
                <th>Business</th>
                <th>Detail</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
            <tr>
                <td>
                    <span class="badge {{ $record['type'] === 'shopping' ? 'badge-blue' : ($record['type'] === 'booking' ? 'badge-purple' : 'badge-green') }}">
                        {{ $record['type_label'] }}
                    </span>
                </td>
                <td class="font-medium text-white">{{ $record['reference'] }}</td>
                <td>
                    <div class="text-sm font-medium text-white">{{ $record['customer_name'] }}</div>
                    <div class="text-xs text-slate-500">{{ $record['customer_phone'] ?? '' }}</div>
                </td>
                <td class="text-sm">{{ $record['business'] }}</td>
                <td class="text-sm text-slate-400">{{ $record['detail'] }}</td>
                <td class="text-sm font-medium">
                    {{ $record['amount'] !== null ? '₹'.number_format($record['amount'], 2) : '—' }}
                </td>
                <td>
                    <span class="badge {{ $statusColors[$record['status']] ?? 'badge-yellow' }}">
                        {{ ucfirst(str_replace('_', ' ', $record['status'])) }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $paymentColors[$record['payment_status']] ?? 'badge-yellow' }}">
                        {{ ucfirst(str_replace('_', ' ', $record['payment_status'] ?? 'pending')) }}
                    </span>
                </td>
                <td class="text-slate-400 text-xs">{{ optional($record['created_at'])->format('M d, Y') }}</td>
                <td>
                    @if(! empty($record['delete_url']))
                        <form method="POST" action="{{ $record['delete_url'] }}" data-confirm="Delete this {{ $record['type_label'] }} record?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300 text-sm font-medium">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center text-slate-500 py-12">No records found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $records->withQueryString()->links() }}
</div>
@endsection
