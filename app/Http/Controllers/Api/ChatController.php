<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function conversations(Request $request)
    {
        $userId = $request->user()->id;
        $conversations = Conversation::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('business_owner_id', $userId);
        })
            ->with(['business:id,name,slug', 'user:id,name,avatar', 'lastMessage'])
            ->latest('last_message_at')
            ->get()
            ->map(function ($c) use ($userId) {
                $lastMsg = $c->lastMessage;

                return [
                    'id' => $c->id,
                    'business' => $c->business,
                    'last_message' => $lastMsg?->body,
                    'last_message_time' => $lastMsg?->created_at?->diffForHumans(),
                    'unread_count' => $c->messages()->where('sender_id', '!=', $userId)->where('is_read', false)->count(),
                ];
            });

        return response()->json(compact('conversations'));
    }

    public function show(Conversation $conversation, Request $request)
    {
        $userId = $request->user()->id;
        if ($conversation->user_id !== $userId && $conversation->business_owner_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = $conversation->messages()
            ->with('sender')
            ->oldest()
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'message' => $m->body,
                'is_mine' => $m->sender_id === $userId,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return response()->json(compact('conversation', 'messages'));
    }

    public function store(Business $business, Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $userId = $request->user()->id;

        $conversation = Conversation::firstOrCreate(
            ['business_id' => $business->id, 'user_id' => $userId],
            ['business_owner_id' => $business->created_by]
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'body' => $request->message,
        ]);

        $conversation->update(['last_message_at' => now()]);

        NotificationService::messageReceived($conversation, $request->message, $userId);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'business' => ['id' => $business->id, 'name' => $business->name, 'slug' => $business->slug],
            ],
            'message' => [
                'id' => $message->id,
                'message' => $message->body,
                'is_mine' => true,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function reply(Conversation $conversation, Request $request)
    {
        $userId = $request->user()->id;
        if ($conversation->user_id !== $userId && $conversation->business_owner_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'body' => $request->message,
        ]);

        $conversation->update(['last_message_at' => now()]);

        NotificationService::messageReceived($conversation, $request->message, $userId);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'message' => $message->body,
                'is_mine' => true,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ]);
    }
}
