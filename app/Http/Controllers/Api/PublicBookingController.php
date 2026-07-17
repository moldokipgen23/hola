<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicBookingController extends Controller
{
    public function storeBooking(Request $request, $slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();

        if (! $business->hasBookingsModule()) {
            return response()->json(['message' => 'Bookings not available for this business.'], 422);
        }

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'time_slot_id' => 'nullable|exists:time_slots,id',
            'booking_type' => 'nullable|in:standard,time_slot',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'end_time' => 'required',
            'duration_minutes' => 'required|integer|min:15',
            'notes' => 'nullable|string',
        ]);

        // Verify service belongs to this business
        $service = $business->services()->where('id', $validated['service_id'])->first();
        if (! $service) {
            return response()->json(['message' => 'Invalid service.'], 422);
        }

        // For time-slot bookings, check capacity instead of overlapping times
        if (! empty($validated['time_slot_id'])) {
            $slot = TimeSlot::where('service_id', $service->id)->find($validated['time_slot_id']);
            if (! $slot) {
                return response()->json(['message' => 'Invalid time slot.'], 422);
            }

            $bookedCount = Booking::where('time_slot_id', $slot->id)
                ->where('booking_date', $validated['booking_date'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();

            if ($bookedCount >= $slot->capacity) {
                return response()->json(['message' => 'This time slot is fully booked. Please choose another.'], 422);
            }

            $validated['total_price'] = $slot->price_override ?? $service->price;
        } else {
            // Check for overlapping bookings (standard booking)
            $overlap = Booking::where('business_id', $business->id)
                ->where('booking_date', $validated['booking_date'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->where(function ($q) use ($validated) {
                    $q->where(function ($q2) use ($validated) {
                        $q2->where('start_time', '<', $validated['end_time'])
                            ->where('end_time', '>', $validated['start_time']);
                    });
                })
                ->exists();

            if ($overlap) {
                return response()->json(['message' => 'This time slot is already booked. Please choose another time.'], 422);
            }

            $validated['total_price'] = $service->price;
        }

        $validated['business_id'] = $business->id;
        $validated['status'] = 'pending';

        if ($request->user()) {
            $validated['user_id'] = $request->user()->id;
        }

        $booking = Booking::create($validated);
        $booking->load('service');

        return response()->json([
            'message' => 'Booking request submitted. Awaiting confirmation.',
            'booking' => $booking,
        ], 201);
    }

    public function storeOrder(Request $request, $slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();

        if (! $business->hasOrdersModule()) {
            return response()->json(['message' => 'Orders not available for this business.'], 422);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'delivery_address' => 'nullable|string',
            'delivery_method' => 'nullable|in:delivery,pickup',
            'delivery_time_slot' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        // Verify all products belong to this business
        $productIds = collect($validated['items'])->pluck('product_id');
        $products = Product::where('business_id', $business->id)
            ->whereIn('id', $productIds)
            ->get();

        if ($products->count() !== $productIds->count()) {
            return response()->json(['message' => 'Invalid products in order.'], 422);
        }

        $orderNumber = 'ORD-'.strtoupper(Str::random(8));

        $orderData = [
            'business_id' => $business->id,
            'order_number' => $orderNumber,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'delivery_address' => $validated['delivery_address'] ?? null,
            'delivery_method' => $request->delivery_method ?? 'delivery',
            'delivery_time_slot' => $validated['delivery_time_slot'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 0,
            'total' => 0,
        ];

        if ($request->user()) {
            $orderData['user_id'] = $request->user()->id;
        }

        $deliveryFee = 0;
        if ($request->delivery_method === 'delivery') {
            $deliveryZone = $business->deliveryZones()->where('is_active', true)->first();
            if (! $deliveryZone) {
                return response()->json(['message' => 'Delivery is not available for this business.'], 422);
            }
            $deliveryFee = $deliveryZone->delivery_fee;
        }
        $orderData['delivery_fee'] = $deliveryFee;

        $order = Order::create($orderData);

        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $product = $products->find($item['product_id']);

            if ($product->stock !== null && $product->stock < $item['quantity']) {
                $order->delete();

                return response()->json([
                    'message' => "Insufficient stock for {$product->name}. Available: {$product->stock}",
                ], 422);
            }

            $totalPrice = $product->price * $item['quantity'];
            $subtotal += $totalPrice;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
                'total_price' => $totalPrice,
            ]);

            if ($product->stock !== null) {
                $product->decrement('stock', $item['quantity']);
            }
        }

        $order->update([
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $subtotal + $deliveryFee,
        ]);

        $order->load('items');

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => $order,
        ], 201);
    }
}
