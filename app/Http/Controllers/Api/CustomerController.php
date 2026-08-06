<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Order;
use App\Services\BookingWorkflowService;
use App\Services\OrderPlacementService;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function myOrders(Request $request)
    {
        return response()->json([
            'orders' => Order::where('user_id', $request->user()->id)
                ->with(['business:id,name,slug,photos', 'items'])
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    public function showOrder(Request $request, $id)
    {
        $order = Order::with([
            'business:id,name,slug,photos,phone,whatsapp',
            'items' => fn ($q) => $q->with('product:id,name,images'),
        ])->findOrFail($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['order' => $order]);
    }

    public function trackOrder(Request $request, $id)
    {
        $order = Order::with('business:id,name,slug,photos,phone,whatsapp')->findOrFail($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['tracking' => $order->trackingTimeline()]);
    }

    public function lookupOrder(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'client_reference' => 'required|string|max:64',
        ]);

        $order = Order::with(['business:id,name,slug,photos,phone,whatsapp', 'items'])
            ->where('customer_phone', $validated['phone'])
            ->where('client_reference', $validated['client_reference'])
            ->orderByDesc('created_at')
            ->first();

        if (! $order) {
            return response()->json(['message' => 'No order found for this phone and reference.', 'order' => null], 404);
        }

        return response()->json([
            'order' => [
                'id' => $order->id,
                'client_reference' => $order->client_reference,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'delivery_method' => $order->delivery_method,
                'subtotal' => $order->subtotal,
                'tax' => $order->tax,
                'delivery_fee' => $order->delivery_fee,
                'discount' => $order->discount,
                'total' => $order->total,
                'estimated_ready_at' => $order->estimated_ready_at?->toIso8601String(),
                'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                'ready_at' => $order->ready_at?->toIso8601String(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'business' => $order->business?->only(['id', 'name', 'slug', 'photos', 'phone', 'whatsapp']),
                'items' => $order->items,
            ],
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

    public function lookupBooking(Request $request)
    {
        // Require both phone AND the booking reference so a phone number alone
        // cannot enumerate strangers' booking histories.
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'client_reference' => 'required|string|max:64',
        ]);

        $booking = Booking::with(['business:id,name,slug,photos', 'service:id,name,price'])
            ->where('customer_phone', $validated['phone'])
            ->where('client_reference', $validated['client_reference'])
            ->orderByDesc('created_at')
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'No booking found for this phone and reference.', 'booking' => null], 404);
        }

        return response()->json([
            'booking' => [
                'id' => $booking->id,
                'client_reference' => $booking->client_reference,
                'booking_type' => $booking->booking_type,
                'status' => $booking->status,
                'payment_status' => $booking->payment_status,
                'booking_date' => $booking->booking_date?->toDateString(),
                'check_in_date' => $booking->check_in_date?->toDateString(),
                'check_out_date' => $booking->check_out_date?->toDateString(),
                'start_time' => $booking->start_time?->format('H:i'),
                'total_price' => $booking->total_price,
                'business' => $booking->business?->only(['id', 'name', 'slug', 'photos']),
                'service' => $booking->service?->only(['id', 'name', 'price']),
            ],
        ]);
    }

    public function cancelOrder(Request $request, $id, OrderWorkflowService $workflow)
    {
        $order = Order::with('business')->findOrFail($id);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);
        $order = $workflow->transition($order, 'cancelled', $request->reason);

        return response()->json(['message' => 'Order cancelled.', 'order' => $order]);
    }

    public function cancelBooking(Request $request, $id, BookingWorkflowService $workflow)
    {
        $booking = Booking::with(['business', 'service'])->findOrFail($id);

        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);
        $booking = $workflow->cancelByCustomer($booking, $request->reason);

        return response()->json(['message' => 'Booking cancelled.', 'booking' => $booking]);
    }

    public function showBooking(Request $request, $id)
    {
        $booking = Booking::with(['business:id,name,slug,photos,phone', 'service', 'timeSlot'])->findOrFail($id);

        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['booking' => $booking]);
    }

    public function rescheduleBooking(Request $request, $id, BookingWorkflowService $workflow)
    {
        $booking = Booking::with(['business', 'service'])->findOrFail($id);

        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'to_date' => 'required|date',
            'to_time' => 'nullable|date_format:H:i',
            'to_slot_id' => 'nullable|integer',
            'reason' => 'nullable|string|max:500',
        ]);

        $booking = $workflow->reschedule(
            $booking,
            $validated['to_date'],
            $validated['to_time'] ?? null,
            isset($validated['to_slot_id']) ? (string) $validated['to_slot_id'] : null,
            $validated['reason'] ?? null,
        );

        return response()->json(['message' => 'Booking rescheduled.', 'booking' => $booking]);
    }

    public function reorder(Request $request, $id, OrderPlacementService $orders)
    {
        $original = Order::with('items')->findOrFail($id);

        if ($original->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($original->status !== 'delivered') {
            return response()->json(['message' => 'Only delivered orders can be reordered.'], 422);
        }

        $business = Business::active()->inServiceableArea()->findOrFail($original->business_id);
        if (! $business->hasOrdersModule()) {
            throw ValidationException::withMessages(['order' => 'This business is not accepting orders now.']);
        }

        if ($original->items->contains(fn ($item) => $item->product_id === null)) {
            throw ValidationException::withMessages(['items' => 'Some products are no longer available. Build a new cart instead.']);
        }

        if ($original->delivery_method === 'delivery' && ! $original->delivery_pincode) {
            throw ValidationException::withMessages(['pincode' => 'Re-enter your delivery pincode in a new cart.']);
        }

        $result = $orders->place(
            $business,
            $original->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])->all(),
            [
                'name' => $original->customer_name,
                'phone' => $original->customer_phone,
                'email' => $original->customer_email,
                'notes' => $original->notes,
            ],
            [
                'delivery_method' => $original->delivery_method,
                'delivery_address' => $original->delivery_address,
                'delivery_time_slot' => $original->delivery_time_slot,
                'pincode' => $original->delivery_pincode,
                'latitude' => $original->customer_latitude !== null ? (float) $original->customer_latitude : null,
                'longitude' => $original->customer_longitude !== null ? (float) $original->customer_longitude : null,
            ],
            $request->user()->id,
            'reorder-'.$original->id.'-'.$request->user()->id.'-'.now()->format('YmdHis'),
        );

        return response()->json([
            'message' => 'Order recreated with current prices and availability. Pay by cash/COD.',
            'order' => $result['order'],
            'payment_mode' => 'offline',
        ], $result['duplicate'] ? 200 : 201);
    }
}
