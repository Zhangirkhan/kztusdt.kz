<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExchangeOrder;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class SupportChatTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_client_can_send_message_and_admin_can_reply(): void
    {
        $client = $this->createClient();
        $admin = $this->createStaff('exchange_admin');
        $order = $this->createOrderForUser($client);

        $this->actingAs($client)
            ->postJson("/api/support/chat/orders/{$order->id}/messages", ['body' => 'Здравствуйте, вопрос по курсу'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Здравствуйте, вопрос по курсу')
            ->assertJsonPath('auto_reply.body', 'Спасибо за обращение! Администратор ответит в ближайшее время — пожалуйста, ожидайте.');

        $conversation = SupportConversation::query()->where('exchange_order_id', $order->id)->firstOrFail();

        $this->assertDatabaseHas('support_messages', [
            'support_conversation_id' => $conversation->id,
            'sender_role' => SupportMessage::ROLE_ADMIN,
            'body' => 'Спасибо за обращение! Администратор ответит в ближайшее время — пожалуйста, ожидайте.',
        ]);

        $this->actingAs($client)
            ->postJson("/api/support/chat/orders/{$order->id}/messages", ['body' => 'Уточняющий вопрос'])
            ->assertCreated()
            ->assertJsonMissingPath('auto_reply');

        $this->actingAsAdmin($admin)
            ->post(route('admin.support.messages.store', $conversation), ['body' => 'Добрый день, чем помочь?'])
            ->assertRedirect(route('admin.support.show', $conversation));

        $this->assertDatabaseHas('support_messages', [
            'support_conversation_id' => $conversation->id,
            'sender_role' => SupportMessage::ROLE_ADMIN,
            'body' => 'Добрый день, чем помочь?',
        ]);
    }

    public function test_each_order_has_separate_chat_thread(): void
    {
        $client = $this->createClient();
        $this->createStaff('exchange_admin');

        $orderA = $this->createOrderForUser($client);
        $orderB = $this->createOrderForUser($client);

        $this->actingAs($client)
            ->postJson("/api/support/chat/orders/{$orderA->id}/messages", ['body' => 'Вопрос по заявке A'])
            ->assertCreated();

        $this->actingAs($client)
            ->getJson("/api/support/chat/orders/{$orderB->id}")
            ->assertOk()
            ->assertJsonPath('messages', []);

        $this->actingAs($client)
            ->getJson("/api/support/chat/orders/{$orderA->id}")
            ->assertOk()
            ->assertJsonCount(2, 'messages');
    }

    public function test_client_sees_unread_admin_messages_for_order(): void
    {
        $client = $this->createClient();
        $admin = $this->createStaff('exchange_admin');
        $order = $this->createOrderForUser($client);

        $conversation = SupportConversation::query()->create([
            'user_id' => $client->id,
            'exchange_order_id' => $order->id,
            'last_message_at' => now(),
        ]);

        SupportMessage::query()->create([
            'support_conversation_id' => $conversation->id,
            'sender_id' => $admin->id,
            'sender_role' => SupportMessage::ROLE_ADMIN,
            'body' => 'Ответ поддержки',
        ]);

        $this->actingAs($client)
            ->getJson("/api/support/chat/orders/{$order->id}/unread")
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_admin_inbox_lists_conversations(): void
    {
        $client = $this->createClient();
        $admin = $this->createStaff('exchange_admin');
        $order = $this->createOrderForUser($client);

        $conversation = SupportConversation::query()->create([
            'user_id' => $client->id,
            'exchange_order_id' => $order->id,
            'last_message_at' => now(),
        ]);

        SupportMessage::query()->create([
            'support_conversation_id' => $conversation->id,
            'sender_id' => $client->id,
            'sender_role' => SupportMessage::ROLE_CLIENT,
            'body' => 'Нужна помощь',
        ]);

        $this->actingAsAdmin($admin)
            ->get('/admin/support')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Support/Index')
                ->has('conversations', 1)
                ->where('conversations.0.last_message_preview', 'Нужна помощь')
                ->where('conversations.0.order.id', $order->id));
    }

    public function test_client_support_chat_page_renders_with_safe_back_url(): void
    {
        $client = $this->createClient();
        $order = $this->createOrderForUser($client);

        $this->actingAs($client)
            ->get("/ru/support/chat?order={$order->id}&back=/exchange/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Support/Chat')
                ->where('orderId', $order->id)
                ->where('backUrl', "/exchange/orders/{$order->id}")
                ->where('needsPaymentProof', true)
                ->where('canUploadProof', true));

        $this->actingAs($client)
            ->get("/ru/support/chat?order={$order->id}&back=//evil.test/phish")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Support/Chat')
                ->where('backUrl', "/exchange/orders/{$order->id}"));
    }

    private function createOrderForUser(User $user): ExchangeOrder
    {
        return ExchangeOrder::query()->create([
            'user_id' => $user->id,
            'direction' => ExchangeOrder::DIRECTION_BUY,
            'status' => ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT,
            'rate' => '505',
            'fiat_amount' => '101000',
            'crypto_amount' => '199',
            'fee_percent' => '0.5',
            'fee_amount' => '1',
        ]);
    }
}
