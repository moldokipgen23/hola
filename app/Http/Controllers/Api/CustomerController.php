<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    protected const VALID_ORDER_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function myOrders(Request $request)
    {
        return response()->json([
            'orders' => Order::where('user_id', $request->user()->id)
                ->with(['business:id,name,slug,photos', 'items'])
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    public function myBookings(Request $request)
    {
        return response()->json([
            'bookings' => Booking::where('user_id', $request->user()->id)
                ->with(['business:id,name,slug,photos', 'service:id,name,price'])
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    public function cancelOrder(Request $request, $id)
    {
        $order = Order::with('business')->findOrFail($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $this->canTransition($order->status, 'cancelled')) {
            return response()->json(['message' => 'Order cannot be cancelled at this stage.'], 422);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->reason,
            'cancelled_at' => now(),
        ]);

        return response()->json(['message' => 'Order cancelled.', 'order' => $order]);
    }

    public function cancelBooking(Request $request, $id)
    {
        $booking = Booking::with('business')->findOrFail($id);

        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Booking cannot be cancelled at this stage.'], 422);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->reason,
            'cancelled_at' => now(),
        ]);

        return response()->json(['message' => 'Booking cancelled.', 'booking' => $booking]);
    }

    public function reorder(Request $request, $id)
    {
        $original = Order::with('items')->findOrFail($id);

        if ($original->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($original->status !== 'delivered') {
            return response()->json(['message' => 'Only delivered orders can be reordered.'], 422);
        }

        $order = Order::create([
            'business_id' => $original->business_id,
            'user_id' => $original->user_id,
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'customer_name' => $original->customer_name,
            'customer_phone' => $original->customer_phone,
            'customer_email' => $original->customer_email,
            'delivery_address' => $original->delivery_address,
            'delivery_method' => $original->delivery_method,
            'delivery_time_slot' => $original->delivery_time_slot,
            'subtotal' => $original->subtotal,
            'tax' => $original->tax,
            'delivery_fee' => $original->delivery_fee,
            'discount' => $original->discount,
            'total' => $original->total,
            'status' => 'pending',
            'payment_status' => 'pending',
            'notes' => $original->notes,
            'metadata' => $original->metadata,
        ]);

        foreach ($original->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]);
        }

        $order->load('items');

        return response()->json(['message' => 'Order recreated.', 'order' => $order], 201);
    }

    protected function canTransition(string $from, string $to): bool
    {
        $allowed = self::VALID_ORDER_TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed);
    }
}
