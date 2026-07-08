<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate(20);

        $unreadCount = $request->user()->notifications()
            ->where('is_read', false)
            ->count();

        return response()->json(compact('notifications', 'unreadCount'));
    }

    public function markRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
