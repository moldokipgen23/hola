<?php

namespace App\Http\Controllers\Integration;

use App\Models\Order;
use App\Services\IntegrationAuditService;
use App\Services\IntegrationWebhookService;
use Illuminate\Http\Request;

class OrderController extends BaseController
{
    public function index(Request $request)
    {
        $orders = Order::with('business:id,name', 'items')
            ->when($request->business_id, fn ($q, $v) => $q->where('business_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->payment_status, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return $this->ok($orders);
    }

    public function show($id)
    {
        return $this->ok(Order::with('business:id,name', 'items')->findOrFail($id));
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::with('business')->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,preparing,ready,out_for_delivery,delivered,cancelled',
            'cancellation_reason' => 'nullable|string',
        ]);

        $order->update($validated);

        $ts = match ($validated['status']) {
            'confirmed' => 'confirmed_at',
            'delivered' => 'delivered_at',
            'cancelled' => 'cancelled_at',
            default => null,
        };
        if ($ts) {
            $order->update([$ts => now()]);
        }

        IntegrationAuditService::log($request, 'order.status_updated', 'order', (string) $order->id, $validated);

        app(IntegrationWebhookService::class)->dispatchForTenant(
            $request->input('integration_tenant_type', 'hola'),
            $request->input('integration_tenant_id'),
            'order.updated',
            $order->fresh()->load('items')->toArray()
        );

        return $this->ok($order->fresh()->load('items'));
    }
}
