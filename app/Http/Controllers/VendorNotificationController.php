<?php

namespace App\Http\Controllers;

use App\Models\VendorNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorNotificationController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->user()->currentBusiness?->id;

        if (!$businessId) {
            return response()->json(['message' => 'No active business'], 404);
        }

        $notifications = VendorNotification::forBusiness($businessId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $unreadCount = VendorNotification::forBusiness($businessId)
            ->unread()
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function show(VendorNotification $notification)
    {
        if (!$notification->is_read) {
            $notification->markRead();
        }

        return response()->json(['notification' => $notification]);
    }

    public function markRead(VendorNotification $notification): JsonResponse
    {
        $notification->markRead();

        return response()->json(['success' => true, 'message' => 'Notification marked as read']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $businessId = $request->user()->currentBusiness?->id;

        if (!$businessId) {
            return response()->json(['message' => 'No active business'], 404);
        }

        VendorNotification::forBusiness($businessId)
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $businessId = $request->user()->currentBusiness?->id;

        if (!$businessId) {
            return response()->json(['unread_count' => 0]);
        }

        $count = VendorNotification::forBusiness($businessId)
            ->unread()
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function destroy(VendorNotification $notification): JsonResponse
    {
        $notification->delete();

        return response()->json(['success' => true, 'message' => 'Notification deleted']);
    }
}
