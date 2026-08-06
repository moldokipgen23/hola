@extends('layouts.admin')

@section('title', 'Order #'.$order->order_number)
@section('header', 'Order #'.$order->order_number)

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.orders') }}" class="btn-ghost">← Back to Orders</a>
</div>

@if($errors->any())
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl mb-4">
        <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left: order + items --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="glass-card p-6 rounded-xl">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-white font-semibold text-lg">{{ $order->business->name ?? 'Business' }}</h3>
                    <p class="text-slate-500 text-sm">{{ $order->customer_name }} · {{ $order->customer_phone ?? '—' }}</p>
                </div>
                <div class="text-right">
                    <span class="badge {{ $order->status === 'delivered' ? 'badge-green' : ($order->status === 'cancelled' ? 'badge-red' : 'badge-blue') }}">
                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                    </span>
                    <div class="text-xs text-slate-500 mt-1">Placed {{ $order->created_at->format('M d, Y h:i A') }}</div>
                </div>
            </div>

            @if($order->delivery_address)
                <div class="mb-4 text-sm text-slate-400">
                    <strong class="text-slate-300">Delivery:</strong> {{ $order->delivery_address }}
                    @if($order->delivery_pincode) · {{ $order->delivery_pincode }}@endif
                </div>
            @endif
            @if($order->delivery_method)
                <div class="flex gap-2 text-xs mb-4">
                    <span class="badge {{ $order->delivery_method === 'pickup' ? 'badge-yellow' : 'badge-blue' }}">{{ ucfirst($order->delivery_method) }}</span>
                    @if($order->delivery_time_slot)
                        <span class="badge badge-blue">{{ $order->delivery_time_slot }}</span>
                    @endif
                </div>
            @endif

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->items as $item)
                        <tr>
                            <td class="text-sm">{{ $item->name ?? $item->product->name ?? 'Item' }}</td>
                            <td class="text-sm">{{ $item->quantity }}</td>
                            <td class="text-sm">₹{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-sm">₹{{ number_format($item->unit_price * $item->quantity, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500 py-6">No items.</td></tr>
                    @endforelse
                    <tr>
                        <td colspan="3" class="text-right text-sm font-medium text-slate-300">Total</td>
                        <td class="text-sm font-bold text-white">₹{{ number_format($order->total, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($order->transactions->isNotEmpty())
        <div class="glass-card p-6 rounded-xl">
            <h4 class="text-white font-semibold mb-3">Transactions</h4>
            <table class="data-table">
                <thead>
                    <tr><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    @foreach($order->transactions as $transaction)
                        <tr>
                            <td class="text-sm">{{ ucfirst($transaction->type) }}</td>
                            <td class="text-sm">₹{{ number_format($transaction->amount, 2) }}</td>
                            <td class="text-sm"><span class="badge badge-green">{{ ucfirst($transaction->status) }}</span></td>
                            <td class="text-sm text-slate-400">{{ $transaction->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Right: actions + timeline --}}
    <div class="space-y-6">
        <div class="glass-card p-6 rounded-xl">
            <h4 class="text-white font-semibold mb-3">Actions</h4>

            @php
                $nextStatuses = [
                    'pending' => ['confirmed' => 'Confirm', 'cancelled' => 'Cancel'],
                    'confirmed' => ['preparing' => 'Start Preparing', 'cancelled' => 'Cancel'],
                    'preparing' => ['ready' => 'Mark Ready', 'cancelled' => 'Cancel'],
                    'ready' => $order->delivery_method === 'delivery'
                        ? ['out_for_delivery' => 'Dispatch for Delivery', 'cancelled' => 'Cancel']
                        : ['delivered' => 'Mark Delivered', 'cancelled' => 'Cancel'],
                    'out_for_delivery' => ['delivered' => 'Mark Delivered'],
                ];
            @endphp
            @foreach(($nextStatuses[$order->status] ?? []) as $next => $label)
                <form method="POST" action="{{ route('admin.orders.status', $order->id) }}" class="mb-2">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" value="{{ $next }}">
                    <button type="submit" class="btn-primary w-full">{{ $label }}</button>
                </form>
            @endforeach

            @if($order->payment_status !== 'paid' && ! in_array($order->status, ['cancelled', 'refunded']))
                <form method="POST" action="{{ route('admin.orders.payment-status', $order->id) }}" class="mb-2">
                    @csrf @method('PUT')
                    <input type="hidden" name="payment_status" value="paid">
                    <button type="submit" class="btn-ghost w-full">Mark Cash Collected</button>
                </form>
            @endif

            @if(in_array($order->status, ['delivered', 'cancelled']) && $order->payment_status === 'paid')
                <form method="POST" action="{{ route('admin.orders.refund', $order->id) }}" class="mb-2" data-confirm="Refund ₹{{ number_format($order->total, 2) }} for this order?">
                    @csrf @method('PUT')
                    <button type="submit" class="btn-danger w-full">Refund Order</button>
                </form>
            @endif
        </div>

        <div class="glass-card p-6 rounded-xl">
            <h4 class="text-white font-semibold mb-3">Timeline</h4>
            <div class="space-y-2 text-sm">
                @php
                    $timeline = [
                        'confirmed' => 'Confirmed',
                        'preparing' => 'Preparing',
                        'ready' => 'Ready',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ];
                @endphp
                @foreach($timeline as $key => $label)
                    @if($order->{$key.'_at'})
                        <div class="flex justify-between">
                            <span class="text-slate-300">{{ $label }}</span>
                            <span class="text-slate-500">{{ \Carbon\Carbon::parse($order->{$key.'_at'})->format('M d, h:i A') }}</span>
                        </div>
                    @endif
                @endforeach
                @if($order->cancellation_reason)
                    <div class="text-xs text-red-400 mt-2">Cancel reason: {{ $order->cancellation_reason }}</div>
                @endif
                @if($order->refund_reason)
                    <div class="text-xs text-orange-400 mt-2">Refund reason: {{ $order->refund_reason }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection