<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Business;
use App\Models\ClaimRequest;
use App\Models\Review;
use App\Models\Conversation;

class NotificationService
{
    public static function create(User $user, string $type, string $title, string $body, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    // Claim Notifications
    public static function claimSubmitted(ClaimRequest $claim): void
    {
        // Notify all admins
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
            "Your claim for \"{$claim->business->name}\" was rejected." . ($claim->admin_notes ? " Reason: {$claim->admin_notes}" : ''),
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
    public static function messageReceived(Conversation $conversation, string $message): void
    {
        // Notify the business owner if user sent the message
        if ($conversation->user_id && $conversation->business_owner_id !== $conversation->user_id) {
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

        // Notify the user if owner sent the message
        if ($conversation->user_id) {
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

    public static function reportResolved(\App\Models\Report $report): void
    {
        if ($report->user_id) {
            self::create(
                User::find($report->user_id),
                'report_resolved',
                'Report Resolved',
                "Your report for \"{$report->business->name}\" has been reviewed.",
                ['business_id' => $report->business_id, 'report_id' => $report->id]
            );
        }
    }
}
