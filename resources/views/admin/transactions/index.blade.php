@extends('layouts.admin')

@section('title', 'Transactions')
@section('header', 'Transactions')

@section('content')
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div>
            <select name="type" class="input-dark w-full">
                <option value="">All Types</option>
                <option value="payment" {{ request('type') == 'payment' ? 'selected' : '' }}>Payment</option>
                <option value="refund" {{ request('type') == 'refund' ? 'selected' : '' }}>Refund</option>
            </select>
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div>
            <input type="text" name="payment_method" value="{{ request('payment_method') }}" placeholder="Payment method..." class="input-dark w-full">
        </div>
        <div>
            <button type="submit" class="btn-primary px-6 w-full">Filter</button>
        </div>
    </div>
    <div class="flex gap-2 mt-3">
        <a href="{{ route('admin.transactions') }}" class="btn-ghost">Clear</a>
        <span class="text-slate-500 text-sm self-center ml-2">{{ $transactions->total() }} transactions</span>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment Method</th>
                <th>Payment ID</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $transaction)
                <tr>
                    <td class="text-sm font-mono">#{{ $transaction->id }}</td>
                    <td class="text-sm">{{ $transaction->user->name ?? '-' }}</td>
                    <td class="text-sm capitalize">{{ $transaction->type }}</td>
                    <td class="text-sm font-mono">₹{{ number_format($transaction->amount, 2) }}</td>
                    <td>
                        @if($transaction->status === 'completed')
                            <span class="badge badge-green">Completed</span>
                        @elseif($transaction->status === 'pending')
                            <span class="badge badge-yellow">Pending</span>
                        @else
                            <span class="badge badge-red">Failed</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $transaction->payment_method ?? '-' }}</td>
                    <td class="text-sm text-slate-400 font-mono">{{ $transaction->payment_id ?? '-' }}</td>
                    <td class="text-sm text-slate-400">{{ $transaction->created_at->format('M d, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-slate-400 py-8">No transactions found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($transactions->hasPages())
    <div class="mt-6">{{ $transactions->withQueryString()->links() }}</div>
@endif
@endsection
