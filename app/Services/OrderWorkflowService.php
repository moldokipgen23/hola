<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled', 'rejected'],
        'confirmed' => ['preparing', 'cancelled', 'rejected'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['out_for_delivery', 'cancelled'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
        'rejected' => [],
        'refunded' => [],
    ];

    public function transition(Order $order, string $status, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $status) {
            $lockedOrder = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            $allowed = self::TRANSITIONS[$lockedOrder->status] ?? [];
            if ($lockedOrder->status === 'ready' && $lockedOrder->delivery_method === 'pickup') {
                $allowed = ['delivered', 'cancelled'];
            }
            if ($lockedOrder->status === 'ready' && $lockedOrder->delivery_method === 'delivery') {
                $allowed = ['out_for_delivery', 'cancelled'];
            }

            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition from '{$lockedOrder->status}' to '{$status}'.",
                ]);
            }

            if ($status === 'cancelled' || $status === 'rejected') {
                $this->releaseInventory($lockedOrder);
            }

            $updates = ['status' => $status];
            if ($status === 'cancelled') {
                $updates['cancellation_reason'] = $reason;
            } elseif ($status === 'rejected') {
                $updates['rejection_reason'] = $reason;
            }

            $timestamp = match ($status) {
                'confirmed' => 'confirmed_at',
                'ready' => 'ready_at',
                'delivered' => 'delivered_at',
                'cancelled' => 'cancelled_at',
                'rejected' => 'rejected_at',
                default => null,
            };
            if ($timestamp) {
                $updates[$timestamp] = now();
            }

            $lockedOrder->update($updates);

            return $lockedOrder->fresh()->load('items');
        });
    }

    public function markCashCollected(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if (in_array($lockedOrder->status, ['cancelled', 'refunded'], true)) {
                throw ValidationException::withMessages(['payment_status' => 'A cancelled order cannot be marked as paid.']);
            }

            $lockedOrder->update(['payment_status' => 'paid', 'payment_method' => 'cash']);

            return $lockedOrder->fresh();
        });
    }

    public function releaseInventory(Order $order): void
    {
        if ($order->inventory_released_at) {
            return;
        }

        foreach ($order->items as $item) {
            if (($item->metadata['stock_tracked'] ?? false) && $item->product_id) {
                Product::whereKey($item->product_id)->increment('stock', $item->quantity);
            }
        }

        $order->update(['inventory_released_at' => now()]);
    }
}
