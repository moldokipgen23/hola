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

    public function myBookings(Request $request)
    {
        return response()->json([
            'bookings' => Booking::where('user_id', $request->user()->id)
                ->with(['business:id,name,slug,photos', 'service:id,name,price'])
                ->orderByDesc('created_at')
                ->paginate(20),
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
