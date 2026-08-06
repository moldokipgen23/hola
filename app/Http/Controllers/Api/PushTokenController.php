<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'platform' => 'required|string|in:ios,android,web',
            'device_name' => 'nullable|string|max:255',
        ]);

        $pushToken = PushToken::updateOrCreate(
            ['token' => $validated['token'], 'user_id' => $request->user()->id],
            [
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Push token registered',
            'id' => $pushToken->id,
        ]);
    }

    public function unregister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        PushToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => 'Push token unregistered']);
    }

    public function test(Request $request, PushNotificationService $pushService): JsonResponse
    {
        if (! $pushService->isConfigured()) {
            return response()->json(['message' => 'Push notifications not configured'], 503);
        }

        $result = $pushService->sendToUser(
            $request->user()->id,
            'Test Notification',
            'Push notifications are working!',
            ['type' => 'test']
        );

        return response()->json(['success' => true, 'result' => $result]);
    }
}
