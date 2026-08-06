<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Models\ClaimRequest;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Report;
use App\Models\Review;
use App\Models\ScheduleBooking;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehicleRental;
use Illuminate\Support\Facades\App;

class NotificationService
{
    private static function getPushService(): ?PushNotificationService
    {
        if (App::runningInConsole() || ! App::has('push')) {
            try {
                return App::make(PushNotificationService::class);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    private static function dispatch(User $user, string $type, string $title, string $body, array $data = []): void
    {
        $push = self::getPushService();
        if ($push && $push->isConfigured()) {
            $push->sendToUser($user->id, $title, $body, array_merge($data, ['type' => $type]));
        }
    }

    public static function create(User $user, string $type, string $title, string $body, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        self::dispatch($user, $type, $title, $body, $data);

        return $notification;
    }

    // Claim Notifications
    public static function claimSubmitted(ClaimRequest $claim): void
    {
        $admins = User::whereIn('role', ['super_admin', 'admin'])->get();
        foreach ($admins as $admin) {
            self::create(
                $admin,
                'claim_submitted',
                'New Claim Request',
                "{$claim->user->name} claimed \"{$claim->business->name}\"",
                ['claim_id' => $claim->id, 'business_id' => $claim->business_id]
            );
        }
    }

    public static function claimApproved(ClaimRequest $claim): void
    {
        self::create(
            $claim->user,
            'claim_approved',
            'Claim Approved!',
            "Your claim for \"{$claim->business->name}\" has been approved. You can now manage your business.",
            ['business_id' => $claim->business_id]
        );
    }

    public static function claimRejected(ClaimRequest $claim): void
    {
        self::create(
            $claim->user,
            'claim_rejected',
            'Claim Rejected',
            "Your claim for \"{$claim->business->name}\" was rejected.".($claim->admin_notes ? " Reason: {$claim->admin_notes}" : ''),
            ['business_id' => $claim->business_id]
        );
    }

    // Review Notifications
    public static function reviewCreated(Review $review): void
    {
        $business = $review->business;
        if ($business && $business->created_by) {
            $owner = User::find($business->created_by);
            if ($owner) {
                self::create(
                    $owner,
                    'new_review',
                    'New Review',
                    "{$review->user->name} left a {$review->rating}-star review on \"{$business->name}\"",
                    ['business_id' => $business->id, 'review_id' => $review->id]
                );
            }
        }
    }

    // Message Notifications
    public static function messageReceived(Conversation $conversation, string $message, ?int $senderId = null): void
    {
        if ($conversation->user_id && $conversation->business_owner_id !== $conversation->user_id && $conversation->business_owner_id !== $senderId) {
            $owner = User::find($conversation->business_owner_id);
            if ($owner) {
                self::create(
                    $owner,
                    'new_message',
                    'New Message',
                    "You have a new message regarding \"{$conversation->business->name}\"",
                    ['conversation_id' => $conversation->id, 'business_id' => $conversation->business_id]
                );
            }
        }

        if ($conversation->user_id && $conversation->user_id !== $senderId) {
            $user = User::find($conversation->user_id);
            if ($user) {
                self::create(
                    $user,
                    'new_message',
                    'New Message',
                    "You have a new reply from \"{$conversation->business->name}\"",
                    ['conversation_id' => $conversation->id, 'business_id' => $conversation->business_id]
                );
            }
        }
    }

    // Business Notifications
    public static function businessApproved(Business $business): void
    {
        if ($business->created_by) {
            $owner = User::find($business->created_by);
            if ($owner) {
                self::create(
                    $owner,
                    'business_approved',
                    'Business Approved',
                    "\"{$business->name}\" has been approved and is now live on Eiho One!",
                    ['business_id' => $business->id]
                );
            }
        }
    }

    public static function reportResolved(Report $report): void
    {
        if ($report->user_id && $user = User::find($report->user_id)) {
            self::create(
                $user,
                'report_resolved',
                'Report Resolved',
                "Your report for \"{$report->business->name}\" has been reviewed.",
                ['business_id' => $report->business_id, 'report_id' => $report->id]
            );
        }
    }

    public static function newOrder(Order $order): void
    {
        if ($order->business && $order->business->created_by) {
            $owner = User::find($order->business->created_by);
            if ($owner) {
                self::create(
                    $owner,
                    'new_order',
                    'New Order',
                    "You have a new order #{$order->order_number}",
                    ['order_id' => $order->id, 'business_id' => $order->business_id]
                );

                $push = self::getPushService();
                if ($push) {
                    $push->sendVendorNotification(
                        $order->business_id,
                        'new_order',
                        'New Order',
                        "You have a new order #{$order->order_number} — tap to view",
                        ['order_id' => $order->id]
                    );
                }
            }
        }
    }

    public static function newBooking(Booking $booking): void
    {
        if ($booking->business && $booking->business->created_by) {
            $owner = User::find($booking->business->created_by);
            if ($owner) {
                self::create(
                    $owner,
                    'new_booking',
                    'New Booking',
                    "You have a new booking for \"{$booking->business->name}\"",
                    ['booking_id' => $booking->id, 'business_id' => $booking->business_id]
                );

                $push = self::getPushService();
                if ($push) {
                    $push->sendVendorNotification(
                        $booking->business_id,
                        'new_booking',
                        'New Booking',
                        "New booking for {$booking->business->name} — tap to view",
                        ['booking_id' => $booking->id]
                    );
                }
            }
        }
    }

    public static function bookingStatusChanged(Booking $booking, string $status): void
    {
        $labels = [
            'confirmed' => 'confirmed',
            'cancelled' => 'cancelled',
            'rejected' => 'rejected',
            'completed' => 'completed',
            'no_show' => 'marked no-show',
            'rescheduled' => 'rescheduled',
        ];
        $label = $labels[$status] ?? $status;

        if ($booking->business && $booking->business->created_by) {
            $owner = User::find($booking->business->created_by);
            if ($owner) {
                self::create(
                    $owner,
                    'booking_'.$status,
                    'Booking '.ucfirst($label),
                    "Booking for \"{$booking->business->name}\" was {$label}.",
                    ['booking_id' => $booking->id, 'business_id' => $booking->business_id]
                );
            }
        }

        if ($booking->user_id && $user = User::find($booking->user_id)) {
            self::create(
                $user,
                'booking_'.$status,
                'Booking '.ucfirst($label),
                "Your booking with \"{$booking->business?->name}\" was {$label}.",
                ['booking_id' => $booking->id, 'business_id' => $booking->business_id]
            );
        }
    }

    public static function orderStatusChanged(Order $order, string $status): void
    {
        $labels = [
            'confirmed' => 'confirmed',
            'preparing' => 'being prepared',
            'ready' => 'ready',
            'out_for_delivery' => 'out for delivery',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'rejected' => 'rejected',
            'refunded' => 'refunded',
        ];
        $label = $labels[$status] ?? $status;

        if ($order->business?->created_by && $owner = User::find($order->business->created_by)) {
            self::create(
                $owner,
                'order_'.$status,
                'Order '.ucfirst($label),
                "Order #{$order->order_number} for \"{$order->business->name}\" was {$label}.",
                ['order_id' => $order->id, 'business_id' => $order->business_id]
            );
        }

        if ($order->user_id && $user = User::find($order->user_id)) {
            self::create(
                $user,
                'order_'.$status,
                'Order '.ucfirst($label),
                "Your order #{$order->order_number} from \"{$order->business?->name}\" was {$label}.",
                ['order_id' => $order->id, 'business_id' => $order->business_id]
            );
        }
    }

    public static function newTrip(Trip $trip): void
    {
        if ($trip->business?->created_by && $owner = User::find($trip->business->created_by)) {
            self::create(
                $owner,
                'new_trip',
                'New Transport Request',
                "New trip request from {$trip->customer_name} ({$trip->pickup_location} → {$trip->drop_location}).",
                ['trip_id' => $trip->id, 'business_id' => $trip->business_id]
            );
        }
    }

    public static function tripStatusChanged(Trip $trip, string $status): void
    {
        $labels = [
            'confirmed' => 'confirmed',
            'started' => 'started',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
        ];
        $label = $labels[$status] ?? $status;

        if ($trip->business?->created_by && $owner = User::find($trip->business->created_by)) {
            self::create(
                $owner,
                'trip_'.$status,
                'Trip '.ucfirst($label),
                "Trip to {$trip->drop_location} was {$label}.",
                ['trip_id' => $trip->id, 'business_id' => $trip->business_id]
            );
        }

        if ($trip->user_id && $user = User::find($trip->user_id)) {
            self::create(
                $user,
                'trip_'.$status,
                'Trip '.ucfirst($label),
                "Your trip to {$trip->drop_location} with \"{$trip->business?->name}\" was {$label}.",
                ['trip_id' => $trip->id, 'business_id' => $trip->business_id]
            );
        }
    }

    public static function newSeatBooking(ScheduleBooking $booking): void
    {
        if ($booking->business?->created_by && $owner = User::find($booking->business->created_by)) {
            self::create(
                $owner,
                'new_seat_booking',
                'New Seat Booking',
                'New seat booking for '.implode(', ', (array) $booking->seat_labels)." on {$booking->schedule?->departure_time}.",
                ['schedule_booking_id' => $booking->id, 'business_id' => $booking->business_id]
            );
        }
    }

    public static function seatBookingStatusChanged(ScheduleBooking $booking, string $status): void
    {
        $labels = [
            'confirmed' => 'confirmed',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'no_show' => 'marked no-show',
        ];
        $label = $labels[$status] ?? $status;

        if ($booking->business?->created_by && $owner = User::find($booking->business->created_by)) {
            self::create(
                $owner,
                'seat_booking_'.$status,
                'Seat booking '.ucfirst($label),
                'Seat booking '.implode(', ', (array) $booking->seat_labels)." was {$label}.",
                ['schedule_booking_id' => $booking->id, 'business_id' => $booking->business_id]
            );
        }

        if ($booking->user_id && $user = User::find($booking->user_id)) {
            self::create(
                $user,
                'seat_booking_'.$status,
                'Seat booking '.ucfirst($label),
                "Your seat booking with \"{$booking->business?->name}\" was {$label}.",
                ['schedule_booking_id' => $booking->id, 'business_id' => $booking->business_id]
            );
        }
    }

    public static function newVehicleRental(VehicleRental $rental): void
    {
        if ($rental->business?->created_by && $owner = User::find($rental->business->created_by)) {
            self::create(
                $owner,
                'new_vehicle_rental',
                'New Vehicle Hire',
                "New hire request for {$rental->vehicle?->name} ({$rental->start_date} → {$rental->end_date}).",
                ['vehicle_rental_id' => $rental->id, 'business_id' => $rental->business_id]
            );
        }
    }

    public static function vehicleRentalStatusChanged(VehicleRental $rental, string $status): void
    {
        $labels = [
            'confirmed' => 'confirmed',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
        ];
        $label = $labels[$status] ?? $status;

        if ($rental->business?->created_by && $owner = User::find($rental->business->created_by)) {
            self::create(
                $owner,
                'vehicle_rental_'.$status,
                'Vehicle hire '.ucfirst($label),
                "Hire of {$rental->vehicle?->name} was {$label}.",
                ['vehicle_rental_id' => $rental->id, 'business_id' => $rental->business_id]
            );
        }

        if ($rental->user_id && $user = User::find($rental->user_id)) {
            self::create(
                $user,
                'vehicle_rental_'.$status,
                'Vehicle hire '.ucfirst($label),
                "Your hire of {$rental->vehicle?->name} with \"{$rental->business?->name}\" was {$label}.",
                ['vehicle_rental_id' => $rental->id, 'business_id' => $rental->business_id]
            );
        }
    }
}
