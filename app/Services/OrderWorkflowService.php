<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
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
        'delivered' => ['refunded'],
        'cancelled' => ['refunded'],
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
                'preparing' => 'preparing_at',
                'ready' => 'ready_at',
                'out_for_delivery' => 'out_for_delivery_at',
                'delivered' => 'delivered_at',
                'cancelled' => 'cancelled_at',
                'rejected' => 'rejected_at',
                default => null,
            };
            if ($timestamp) {
                $updates[$timestamp] = now();
            }

            $lockedOrder->update($updates);

            // Record platform commission when an order is delivered (cash collected).
            if ($status === 'delivered' && $lockedOrder->business) {
                try {
                    app(MonetizationService::class)->recordCommission(
                        $lockedOrder->business,
                        Order::class,
                        $lockedOrder->id,
                        (float) ($lockedOrder->total ?? 0),
                    );
                } catch (\Throwable $e) {
                    // Commission must never fail the order workflow.
                }
            }

            NotificationService::orderStatusChanged($lockedOrder, $status);

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

    /**
     * Refund a paid order: set status to `refunded`, stamp refunded_at, record
     * a refund Transaction and release inventory back to the products.
     * Only paid, non-refunded orders can be refunded (server-side amount only).
     */
    public function refund(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $lockedOrder = Order::with('items')->lockForUpdate()->findOrFail($order->id);

            if (! in_array($lockedOrder->status, ['delivered', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot refund an order in '{$lockedOrder->status}' status.",
                ]);
            }

            if ($lockedOrder->payment_status !== 'paid') {
                throw ValidationException::withMessages(['payment_status' => 'Only paid orders can be refunded.']);
            }

            $alreadyRefunded = Transaction::where('billable_type', Order::class)
                ->where('billable_id', $lockedOrder->id)
                ->where('type', 'refund')
                ->where('status', 'completed')
                ->exists();

            if ($alreadyRefunded) {
                throw ValidationException::withMessages(['status' => 'A refund transaction already exists for this order.']);
            }

            Transaction::create([
                'user_id' => $lockedOrder->user_id,
                'billable_type' => Order::class,
                'billable_id' => $lockedOrder->id,
                'type' => 'refund',
                'amount' => $lockedOrder->total,
                'currency' => 'INR',
                'status' => 'completed',
                'payment_method' => $lockedOrder->payment_method,
                'metadata' => [
                    'order_number' => $lockedOrder->order_number,
                    'reason' => $reason,
                ],
            ]);

            $this->releaseInventory($lockedOrder);

            $lockedOrder->update([
                'status' => 'refunded',
                'payment_status' => 'refunded',
                'refunded_at' => now(),
                'refund_reason' => $reason,
            ]);

            NotificationService::orderStatusChanged($lockedOrder, 'refunded');

            return $lockedOrder->fresh()->load('items');
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
