<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ScheduleBooking;
use App\Models\TransportRoute;
use App\Models\VehicleSchedule;
use App\Services\TransportAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Public transport seat-booking endpoints (backend only — Flutter wiring is a
 * later phase). Two tiers: verified vendors are bookable; other transport
 * businesses surface as call-only listings.
 */
class TransportBookingController extends Controller
{
    public function __construct(private readonly TransportAvailabilityService $availability) {}

    /**
     * GET /transport/routes — searchable admin-curated routes.
     */
    public function routes(Request $request)
    {
        $routes = TransportRoute::active()
            ->ordered()
            ->search($request->query('q'))
            ->withCount(['schedules' => fn ($q) => $q->scheduled()->upcoming()])
            ->paginate(30);

        return response()->json(['data' => $routes]);
    }

    /**
     * GET /transport/search?origin=&destination=&date=
     * Find schedules across bookable transport vendors for a route/date.
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'date' => 'nullable|date',
        ]);

        $date = $validated['date'] ?? now()->toDateString();

        $schedules = VehicleSchedule::scheduled()
            ->whereDate('departure_date', $date)
            ->where(function ($q) use ($validated) {
                $q->where(fn ($w) => $w->where('origin', 'like', '%'.$validated['origin'].'%'))
                    ->where(fn ($w) => $w->where('destination', 'like', '%'.$validated['destination'].'%'));
            })
            ->with(['vehicle:id,name,type,image,seats', 'route:id,origin,destination,distance_km,estimated_minutes', 'business:id,name,slug,verification_status,enabled_modules'])
            ->get()
            ->filter(fn (VehicleSchedule $schedule) => $this->availability->isBookable($schedule->business))
            ->sortBy(fn (VehicleSchedule $schedule) => $schedule->departure_time)
            ->map(fn (VehicleSchedule $schedule) => [
                'id' => $schedule->id,
                'origin' => $schedule->origin,
                'destination' => $schedule->destination,
                'departure_date' => $schedule->departure_date->toDateString(),
                'departure_time' => $schedule->departure_time,
                'arrival_estimate' => $this->arrivalEstimate($schedule),
                'distance_km' => $schedule->distance_km ?? $schedule->route?->distance_km,
                'travel_minutes' => $schedule->estimated_minutes ?? $schedule->route?->estimated_minutes,
                'price' => (float) $schedule->price,
                'seats_total' => (int) $schedule->seats_capacity,
                'seats_left' => $this->availability->remainingSeats($schedule),
                'boarding_stops' => $schedule->boarding_stops ?: [],
                'drop_stops' => $schedule->drop_stops ?: [],
                'vehicle' => $schedule->vehicle,
                'business' => $schedule->business->only(['id', 'name', 'slug', 'verification_status']),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $schedules]);
    }

    /**
     * Approximate arrival time = departure + travel minutes (vendor-set first).
     */
    private function arrivalEstimate(VehicleSchedule $schedule): ?string
    {
        $minutes = (int) ($schedule->estimated_minutes ?? $schedule->route?->estimated_minutes);
        if (! $minutes) {
            return null;
        }

        return Carbon::parse($schedule->departure_time)->addMinutes($minutes)->format('H:i');
    }

    /**
     * GET /transport/schedules/{id} — schedule + visual seat map with availability.
     */
    public function showSchedule($id)
    {
        $schedule = VehicleSchedule::scheduled()
            ->with(['vehicle', 'route', 'business:id,name,slug,phone,whatsapp,verification_status,enabled_modules'])
            ->findOrFail($id);

        abort_unless($this->availability->isBookable($schedule->business), 404, 'This departure is not bookable online.');

        return response()->json([
            'data' => $schedule,
            'arrival_estimate' => $this->arrivalEstimate($schedule),
            'seats' => $this->availability->seatMapWithAvailability($schedule),
            'seats_left' => $this->availability->remainingSeats($schedule),
        ]);
    }

    /**
     * POST /transport/schedules/{id}/book — confirm seat booking.
     */
    public function book(Request $request, $id)
    {
        $schedule = VehicleSchedule::scheduled()
            ->with(['business' => fn ($q) => $q->select('id', 'name', 'slug', 'phone', 'whatsapp', 'verification_status', 'enabled_modules')])
            ->findOrFail($id);

        abort_unless($this->availability->isBookable($schedule->business), 404, 'This departure is not bookable online.');

        $validated = $request->validate([
            'seat_labels' => 'required|array|min:1',
            'seat_labels.*' => 'string|max:20',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'client_reference' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $booking = $this->availability->bookSeats(
                $schedule,
                $validated['seat_labels'],
                [
                    'name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'],
                    'email' => $validated['customer_email'] ?? null,
                ],
                $request->user()?->id,
                $validated['client_reference'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'message' => 'Seats requested. Confirm with the operator to lock them in.',
            'booking' => $booking->load('schedule.vehicle'),
            'payment_mode' => 'offline',
            'business_contact' => [
                'phone' => $schedule->business->phone,
                'whatsapp' => $schedule->business->whatsapp,
            ],
        ], 201);
    }

    /**
     * GET /transport/my-bookings — the signed-in customer's seat bookings.
     */
    public function myBookings(Request $request)
    {
        return response()->json([
            'data' => ScheduleBooking::with(['schedule.vehicle', 'business:id,name,slug'])
                ->where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    /**
     * GET /transport/businesses/{slug} — transport businesses for a route,
     * split into bookable (online) and call-only listings.
     */
    public function businessesForRoute(Request $request, $slug = null)
    {
        $origin = $request->query('origin');
        $destination = $request->query('destination');

        $query = Business::where('enabled_modules->transport', true)
            ->where('is_active', true);

        if ($origin && $destination) {
            $query->whereHas('schedules', function ($q) use ($origin, $destination) {
                $q->where('origin', 'like', '%'.$origin.'%')
                    ->where('destination', 'like', '%'.$destination.'%')
                    ->where('status', 'scheduled')
                    ->whereDate('departure_date', '>=', now()->toDateString());
            });
        }

        $businesses = $query->withCount(['schedules' => fn ($q) => $q->scheduled()->upcoming()])
            ->orderBy('name')
            ->get();

        $grouped = $businesses->groupBy(fn (Business $b) => $this->availability->isBookable($b) ? 'bookable' : 'call_only');

        return response()->json([
            'data' => [
                'bookable' => $grouped->get('bookable', collect())->values()->map(fn (Business $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'verification_status' => $b->verification_status,
                    'schedules_count' => $b->schedules_count,
                    'mode' => 'bookable',
                ]),
                'call_only' => $grouped->get('call_only', collect())->values()->map(fn (Business $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'phone' => $b->phone,
                    'whatsapp' => $b->whatsapp,
                    'verification_status' => $b->verification_status,
                    'mode' => 'call_only',
                ]),
            ],
        ]);
    }
}
