<?php

namespace App\Http\Controllers\Integration;

use App\Models\Booking;
use App\Services\IntegrationAuditService;
use App\Services\IntegrationWebhookService;
use Illuminate\Http\Request;

class BookingController extends BaseController
{
    public function index(Request $request)
    {
        $bookings = Booking::with('business:id,name', 'service:id,name')
            ->when($request->business_id, fn ($q, $v) => $q->where('business_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('booking_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('booking_date', '<=', $v))
            ->latest('booking_date')
            ->paginate($request->per_page ?? 20);

        return $this->ok($bookings);
    }

    public function show($id)
    {
        return $this->ok(Booking::with('business:id,name', 'service:id,name')->findOrFail($id));
    }

    public function updateStatus(Request $request, $id)
    {
        $booking = Booking::with('business')->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,completed,cancelled,no_show',
            'cancellation_reason' => 'nullable|string',
        ]);

        $booking->update($validated);

        $ts = match ($validated['status']) {
            'confirmed' => 'confirmed_at',
            'completed' => 'completed_at',
            'cancelled' => 'cancelled_at',
            default => null,
        };
        if ($ts) {
            $booking->update([$ts => now()]);
        }

        IntegrationAuditService::log($request, 'booking.status_updated', 'booking', (string) $booking->id, $validated);

        app(IntegrationWebhookService::class)->dispatchForTenant(
            $request->input('integration_tenant_type', 'hola'),
            $request->input('integration_tenant_id'),
            'booking.updated',
            $booking->fresh()->load('service:id,name')->toArray()
        );

        return $this->ok($booking->fresh()->load('service:id,name'));
    }
}
