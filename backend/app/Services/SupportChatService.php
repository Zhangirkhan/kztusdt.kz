<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExchangeOrder;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SupportChatService
{
    public function getOrCreateConversation(User $user, ExchangeOrder $order): SupportConversation
    {
        $this->assertOrderOwnedByUser($order, $user);

        return SupportConversation::query()->firstOrCreate(
            ['exchange_order_id' => $order->id],
            [
                'user_id' => $user->id,
                'last_message_at' => null,
            ],
        );
    }

    public function findConversationForOrder(User $user, int $orderId): ?SupportConversation
    {
        return SupportConversation::query()
            ->where('exchange_order_id', $orderId)
            ->where('user_id', $user->id)
            ->first();
    }

    public function sendClientMessage(User $user, ExchangeOrder $order, string $body): array
    {
        $conversation = $this->getOrCreateConversation($user, $order);

        $isFirstClientMessage = ! SupportMessage::query()
            ->where('support_conversation_id', $conversation->id)
            ->where('sender_role', SupportMessage::ROLE_CLIENT)
            ->exists();

        $message = $this->storeMessage($conversation, $user, SupportMessage::ROLE_CLIENT, $body);

        $autoReply = $isFirstClientMessage
            ? $this->sendAutoReply($conversation)
            : null;

        return [
            'message' => $message,
            'auto_reply' => $autoReply,
        ];
    }

    public function sendAdminMessage(SupportConversation $conversation, User $admin, string $body): SupportMessage
    {
        return $this->storeMessage($conversation, $admin, SupportMessage::ROLE_ADMIN, $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function clientPayload(User $user, ExchangeOrder $order): array
    {
        $this->assertOrderOwnedByUser($order, $user);

        $conversation = $this->findConversationForOrder($user, $order->id);

        if ($conversation === null) {
            return [
                'conversation_id' => null,
                'order_id' => $order->id,
                'unread_count' => 0,
                'messages' => [],
            ];
        }

        $messages = $this->messagesForConversation($conversation);

        $this->markReadForRole($conversation, SupportMessage::ROLE_ADMIN);

        return [
            'conversation_id' => $conversation->id,
            'order_id' => $order->id,
            'unread_count' => $this->unreadCountForRole($conversation, SupportMessage::ROLE_ADMIN),
            'messages' => $messages->map(fn (SupportMessage $message): array => $this->messagePayload($message))->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function adminInbox(): Collection
    {
        return SupportConversation::query()
            ->with([
                'user:id,name,phone,email',
                'exchangeOrder:id,direction,status,fiat_amount,crypto_amount',
                'latestMessage',
            ])
            ->whereHas('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SupportConversation $conversation): array => $this->adminConversationPayload($conversation));
    }

    /**
     * @return array<string, mixed>
     */
    public function adminThreadPayload(SupportConversation $conversation): array
    {
        $conversation->load([
            'user:id,name,phone,email',
            'exchangeOrder:id,direction,status,fiat_amount,crypto_amount',
        ]);

        $this->markReadForRole($conversation, SupportMessage::ROLE_CLIENT);

        return [
            'conversation' => $this->adminConversationPayload($conversation),
            'messages' => $this->messagesForConversation($conversation)
                ->map(fn (SupportMessage $message): array => $this->messagePayload($message))
                ->values()
                ->all(),
        ];
    }

    public function unreadCountForClient(User $user, ExchangeOrder $order): int
    {
        $this->assertOrderOwnedByUser($order, $user);

        $conversation = $this->findConversationForOrder($user, $order->id);

        if ($conversation === null) {
            return 0;
        }

        return $this->unreadCountForRole($conversation, SupportMessage::ROLE_ADMIN);
    }

    public function totalAdminUnreadCount(): int
    {
        return SupportMessage::query()
            ->where('sender_role', SupportMessage::ROLE_CLIENT)
            ->whereNull('read_at')
            ->count();
    }

    private function assertOrderOwnedByUser(ExchangeOrder $order, User $user): void
    {
        if ($order->user_id !== $user->id) {
            throw new InvalidArgumentException('Нет доступа к этой заявке.');
        }
    }

    private function sendAutoReply(SupportConversation $conversation): SupportMessage
    {
        return $this->storeMessage(
            $conversation,
            $this->autoReplySender(),
            SupportMessage::ROLE_ADMIN,
            'Спасибо за обращение! Администратор ответит в ближайшее время — пожалуйста, ожидайте.',
        );
    }

    private function autoReplySender(): User
    {
        $sender = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('code', ['super_admin', 'exchange_admin']))
            ->orderBy('id')
            ->first();

        if ($sender === null) {
            throw new InvalidArgumentException('Не найден администратор для автоответа.');
        }

        return $sender;
    }

    private function storeMessage(
        SupportConversation $conversation,
        User $sender,
        string $role,
        string $body,
    ): SupportMessage {
        $text = trim($body);

        if ($text === '') {
            throw new InvalidArgumentException('Сообщение не может быть пустым.');
        }

        if (mb_strlen($text) > 2000) {
            throw new InvalidArgumentException('Сообщение слишком длинное (макс. 2000 символов).');
        }

        return DB::transaction(function () use ($conversation, $sender, $role, $text): SupportMessage {
            $message = SupportMessage::query()->create([
                'support_conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'sender_role' => $role,
                'body' => $text,
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });
    }

    /**
     * @return Collection<int, SupportMessage>
     */
    private function messagesForConversation(SupportConversation $conversation): Collection
    {
        return $conversation->messages()
            ->with('sender:id,name')
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    private function markReadForRole(SupportConversation $conversation, string $senderRoleToMarkRead): void
    {
        SupportMessage::query()
            ->where('support_conversation_id', $conversation->id)
            ->where('sender_role', $senderRoleToMarkRead)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function unreadCountForRole(SupportConversation $conversation, string $senderRoleToCount): int
    {
        return SupportMessage::query()
            ->where('support_conversation_id', $conversation->id)
            ->where('sender_role', $senderRoleToCount)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(SupportMessage $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'sender_role' => $message->sender_role,
            'sender_name' => $message->sender?->name,
            'created_at' => $message->created_at?->toIso8601String(),
            'is_mine' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminConversationPayload(SupportConversation $conversation): array
    {
        $latest = $conversation->latestMessage;
        $order = $conversation->exchangeOrder;

        return [
            'id' => $conversation->id,
            'user' => [
                'id' => $conversation->user?->id,
                'name' => $conversation->user?->name,
                'phone' => $conversation->user?->phone,
                'email' => $conversation->user?->email,
            ],
            'order' => $order === null ? null : [
                'id' => $order->id,
                'direction' => $order->direction,
                'status' => $order->status,
                'fiat_amount' => $order->fiat_amount,
                'crypto_amount' => $order->crypto_amount,
            ],
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message_preview' => $latest?->body,
            'last_message_role' => $latest?->sender_role,
            'unread_count' => $this->unreadCountForRole($conversation, SupportMessage::ROLE_CLIENT),
        ];
    }
}
