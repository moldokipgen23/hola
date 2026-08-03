<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\BookingPlacementService;
use App\Services\OrderPlacementService;
use App\Services\LaunchControlService;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    public function storeBooking(Request $request, $slug, BookingPlacementService $bookings, LaunchControlService $launchControl)
    {
        $business = Business::active()->inServiceableArea()->where('slug', $slug)->firstOrFail();

        if (! $business->hasBookingsModule()) {
            return response()->json(['message' => 'Bookings not available for this business.'], 422);
        }
        $businessExperiences = $business->enabled_experiences ?: ['appointment', 'stay', 'turf', 'seat_event'];
        abort_unless(collect(['appointment', 'stay', 'turf', 'seat_event'])
            ->contains(fn (string $experience) => in_array($experience, $businessExperiences, true) && $launchControl->experienceEnabled($experience)), 404);

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'time_slot_id' => 'nullable|integer|exists:time_slots,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'booking_date' => 'nullable|required_without:check_in_date|date|after_or_equal:today',
            'check_in_date' => 'nullable|required_without:booking_date|date|after_or_equal:today',
            'check_out_date' => 'nullable|date|after:check_in_date',
            'start_time' => 'nullable|date_format:H:i',
            'party_size' => 'nullable|integer|min:1|max:100',
            'reservation_units' => 'nullable|integer|min:1|max:100',
            'seat_labels' => 'nullable|array|max:100',
            'seat_labels.*' => 'string|max:20|distinct',
            'client_reference' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:1000',
        ]);

        $result = $bookings->place(
            $business,
            (int) $validated['service_id'],
            [
                'name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'],
                'email' => $validated['customer_email'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
            [
                'booking_date' => $validated['booking_date'] ?? $validated['check_in_date'],
                'check_in_date' => $validated['check_in_date'] ?? null,
                'check_out_date' => $validated['check_out_date'] ?? null,
                'start_time' => $validated['start_time'] ?? null,
                'time_slot_id' => $validated['time_slot_id'] ?? null,
                'party_size' => $validated['party_size'] ?? 1,
                'reservation_units' => $validated['reservation_units'] ?? 1,
                'seat_labels' => $validated['seat_labels'] ?? [],
            ],
            $request->user()?->id,
            $validated['client_reference'] ?? null,
        );

        return response()->json([
            'message' => $result['duplicate']
                ? 'This booking request was already received.'
                : 'Booking request sent. Await confirmation and pay the business directly.',
            'booking' => $result['booking'],
            'duplicate' => $result['duplicate'],
            'payment_mode' => 'offline',
            'business_contact' => [
                'phone' => $business->phone,
                'whatsapp' => $business->whatsapp,
            ],
        ], $result['duplicate'] ? 200 : 201);
    }

    public function storeOrder(Request $request, $slug, OrderPlacementService $orders, LaunchControlService $launchControl)
    {
        $business = Business::active()->inServiceableArea()->where('slug', $slug)->firstOrFail();

        if (! $business->hasOrdersModule()) {
            return response()->json(['message' => 'Orders not available for this business.'], 422);
        }
        $businessExperiences = $business->enabled_experiences ?: ['retail', 'restaurant'];
        abort_unless(collect(['retail', 'restaurant'])
            ->contains(fn (string $experience) => in_array($experience, $businessExperiences, true) && $launchControl->experienceEnabled($experience)), 404);

        $request->merge(['delivery_method' => $request->input('delivery_method', 'delivery')]);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'delivery_address' => 'required_if:delivery_method,delivery|nullable|string|max:1000',
            'delivery_method' => 'required|in:delivery,pickup',
            'delivery_time_slot' => 'nullable|string|max:20',
            'pincode' => 'required_if:delivery_method,delivery|nullable|digits:6',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'area_id' => 'nullable|integer|exists:areas,id',
            'client_reference' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:1000',
        ]);

        $result = $orders->place(
            $business,
            $validated['items'],
            [
                'name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'],
                'email' => $validated['customer_email'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
            [
                'delivery_method' => $validated['delivery_method'],
                'delivery_address' => $validated['delivery_address'] ?? null,
                'delivery_time_slot' => $validated['delivery_time_slot'] ?? null,
                'pincode' => $validated['pincode'] ?? null,
                'latitude' => isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                'longitude' => isset($validated['longitude']) ? (float) $validated['longitude'] : null,
                'area_id' => $validated['area_id'] ?? null,
            ],
            $request->user()?->id,
            $validated['client_reference'] ?? null,
        );

        return response()->json([
            'message' => $result['duplicate']
                ? 'This order was already received.'
                : 'Order request sent. Pay the business directly by cash/COD.',
            'order' => $result['order'],
            'duplicate' => $result['duplicate'],
            'payment_mode' => 'offline',
            'business_contact' => [
                'phone' => $business->phone,
                'whatsapp' => $business->whatsapp,
            ],
        ], $result['duplicate'] ? 200 : 201);
    }
}
