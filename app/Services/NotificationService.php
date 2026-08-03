<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ClaimRequest;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\App;

class NotificationService
{
    private static function getPushService(): ?PushNotificationService
    {
        if (App::runningInConsole() || !App::has('push')) {
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
                    "\"{$business->name}\" has been approved and is now live on Hola!",
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

    public static function newOrder(\App\Models\Order $order): void
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

    public static function newBooking(\App\Models\Booking $booking): void
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
}
