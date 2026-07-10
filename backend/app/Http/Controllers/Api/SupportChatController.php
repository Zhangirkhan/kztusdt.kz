<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeOrder;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class SupportChatController extends Controller
{
    public function __construct(
        private readonly SupportChatService $chatService,
    ) {}

    public function show(Request $request, ExchangeOrder $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $payload = $this->chatService->clientPayload($request->user(), $order);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $payload['messages'] = collect($payload['messages'])
            ->map(function (array $message): array {
                $message['is_mine'] = $message['sender_role'] === 'client';

                return $message;
            })
            ->all();

        return response()->json($payload);
    }

    public function unreadCount(Request $request, ExchangeOrder $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $unreadCount = $this->chatService->unreadCountForClient($request->user(), $order);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json([
            'unread_count' => $unreadCount,
        ]);
    }

    public function store(Request $request, ExchangeOrder $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->chatService->sendClientMessage(
                $request->user(),
                $order,
                $validated['body'],
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $message = $result['message'];
        $payload = [
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_role' => $message->sender_role,
                'sender_name' => $request->user()->name,
                'created_at' => $message->created_at?->toIso8601String(),
                'is_mine' => true,
            ],
        ];

        if ($result['auto_reply'] !== null) {
            $autoReply = $result['auto_reply']->load('sender:id,name');
            $payload['auto_reply'] = [
                'id' => $autoReply->id,
                'body' => $autoReply->body,
                'sender_role' => $autoReply->sender_role,
                'sender_name' => $autoReply->sender?->name,
                'created_at' => $autoReply->created_at?->toIso8601String(),
                'is_mine' => false,
            ];
        }

        return response()->json($payload, 201);
    }
}
