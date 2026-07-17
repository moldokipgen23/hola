@extends('vendor.layouts.dashboard')

@section('title', 'Orders')
@section('header', 'Orders')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Manage Orders</h3>
    <span class="text-slate-500 text-sm">{{ $orders->total() }} orders</span>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="preparing" {{ request('status') == 'preparing' ? 'selected' : '' }}>Preparing</option>
                <option value="ready" {{ request('status') == 'ready' ? 'selected' : '' }}>Ready</option>
                <option value="out_for_delivery" {{ request('status') == 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>
        <div>
            <select name="payment_status" class="input-dark w-full">
                <option value="">All Payment</option>
                <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="refunded" {{ request('payment_status') == 'refunded' ? 'selected' : '' }}>Refunded</option>
            </select>
        </div>
        <div>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by order# or customer..." class="input-dark w-full">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('vendor.orders', $business->id) }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="glass-card rounded-xl overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Order#</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Items</th>
                <th>Total</th>
                <th>Method</th>
                <th>Slot</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td class="font-medium text-white">{{ $order->order_number }}</td>
                <td class="text-sm">{{ $order->customer_name }}</td>
                <td class="text-sm text-slate-400">{{ $order->customer_phone ?? '—' }}</td>
                <td class="text-sm text-slate-400">{{ $order->items->count() }}</td>
                <td class="text-sm font-medium">₹{{ number_format($order->total, 2) }}</td>
                <td>
                    @if($order->delivery_method)
                        <span class="badge {{ $order->delivery_method === 'pickup' ? 'badge-yellow' : 'badge-blue' }}">
                            {{ ucfirst($order->delivery_method) }}
                        </span>
                    @else
                        <span class="text-slate-600">—</span>
                    @endif
                </td>
                <td class="text-sm text-slate-400">{{ $order->delivery_time_slot ?? '—' }}</td>
                <td>
                    @php
                        $statusClasses = [
                            'pending' => 'badge-yellow',
                            'confirmed' => 'badge-blue',
                            'preparing' => 'bg-purple-500/20 text-purple-400',
                            'ready' => 'bg-cyan-500/20 text-cyan-400',
                            'out_for_delivery' => 'bg-indigo-500/20 text-indigo-400',
                            'delivered' => 'badge-green',
                            'cancelled' => 'badge-red',
                        ];
                        $statusLabels = [
                            'pending' => 'Pending',
                            'confirmed' => 'Confirmed',
                            'preparing' => 'Preparing',
                            'ready' => 'Ready',
                            'out_for_delivery' => 'Out for Delivery',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Cancelled',
                        ];
                    @endphp
                    <span class="badge {{ $statusClasses[$order->status] ?? 'badge-yellow' }}">
                        {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
                    </span>
                </td>
                <td>
                    @php
                        $paymentClasses = ['unpaid' => 'badge-red', 'paid' => 'badge-green', 'refunded' => 'bg-orange-500/20 text-orange-400'];
                    @endphp
                    <span class="badge {{ $paymentClasses[$order->payment_status] ?? 'badge-yellow' }}">
                        {{ ucfirst($order->payment_status) }}
                    </span>
                </td>
                <td class="text-slate-400 text-xs">{{ $order->created_at->format('M d, Y') }}</td>
                <td>
                    <div class="flex gap-1 flex-wrap">
                        @if(in_array($order->status, ['pending', 'confirmed']))
                            <form method="POST" action="{{ route('vendor.orders.status', $order->id) }}" class="inline">
                                @csrf @method('PUT')
                                @if($order->status === 'pending')
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20">Confirm</button>
                                @endif
                                @if($order->status === 'confirmed')
                                    <input type="hidden" name="status" value="preparing">
                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-purple-500/10 text-purple-400 hover:bg-purple-500/20">Prepare</button>
                                @endif
                            </form>
                        @endif
                        @if($order->status === 'preparing')
                            <form method="POST" action="{{ route('vendor.orders.status', $order->id) }}" class="inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="status" value="ready">
                                <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-cyan-500/10 text-cyan-400 hover:bg-cyan-500/20">Ready</button>
                            </form>
                        @endif
                        @if($order->status === 'ready')
                            <form method="POST" action="{{ route('vendor.orders.status', $order->id) }}" class="inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="status" value="out_for_delivery">
                                <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500/20">Deliver</button>
                            </form>
                        @endif
                        @if($order->status === 'out_for_delivery')
                            <form method="POST" action="{{ route('vendor.orders.status', $order->id) }}" class="inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="status" value="delivered">
                                <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20">Delivered</button>
                            </form>
                        @endif
                        @if(!in_array($order->status, ['delivered', 'cancelled']))
                            <form method="POST" action="{{ route('vendor.orders.status', $order->id) }}" class="inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20">Cancel</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="11" class="text-center text-slate-500 py-12">No orders found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $orders->withQueryString()->links() }}
</div>
@endsection
