<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Services\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class PushNotificationTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

    public function test_guest_cannot_subscribe(): void
    {
        $this->postJson('/api/push/subscribe', $this->subscriptionPayload())
            ->assertUnauthorized();
    }

    public function test_user_can_subscribe_and_is_idempotent(): void
    {
        $user = $this->createClient();

        $this->actingAs($user)
            ->postJson('/api/push/subscribe', $this->subscriptionPayload())
            ->assertCreated()
            ->assertJsonPath('subscribed', true);

        // Subscribing again with the same endpoint updates, not duplicates.
        $this->actingAs($user)
            ->postJson('/api/push/subscribe', $this->subscriptionPayload())
            ->assertCreated();

        $this->assertSame(1, PushSubscription::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => self::ENDPOINT,
            'user_id' => $user->id,
        ]);
    }

    public function test_subscribe_validates_payload(): void
    {
        $user = $this->createClient();

        $this->actingAs($user)
            ->postJson('/api/push/subscribe', ['endpoint' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys']);
    }

    public function test_user_can_unsubscribe(): void
    {
        $user = $this->createClient();

        PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => self::ENDPOINT,
            'public_key' => 'p256dh-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aesgcm',
        ]);

        $this->actingAs($user)
            ->postJson('/api/push/unsubscribe', ['endpoint' => self::ENDPOINT])
            ->assertOk()
            ->assertJsonPath('subscribed', false);

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_notify_is_safe_without_subscriptions(): void
    {
        $user = $this->createClient();

        // Must not throw even when the user has no push subscriptions.
        app(UserNotificationService::class)->notifyUser($user, "💼 Заголовок\n\nТекст уведомления");

        $this->assertTrue(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionPayload(): array
    {
        return [
            'endpoint' => self::ENDPOINT,
            'keys' => [
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
                'auth' => 'tBHItJI5svbpez7KI4CCXg',
            ],
            'contentEncoding' => 'aesgcm',
        ];
    }
}
