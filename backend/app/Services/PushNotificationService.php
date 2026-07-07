<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\LocaleManager;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Delivers PWA notifications to a user's browsers via the Web Push protocol.
 *
 * Replaces the Telegram bot for user-facing notifications. Sending is
 * best-effort: failures never bubble up into the business flow, and push
 * services that report a subscription as gone (404/410) get pruned.
 */
final class PushNotificationService
{
    public function isConfigured(): bool
    {
        return config('webpush.vapid.public_key') !== ''
            && config('webpush.vapid.private_key') !== '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('webpush.vapid.subject'),
                    'publicKey' => (string) config('webpush.vapid.public_key'),
                    'privateKey' => (string) config('webpush.vapid.private_key'),
                ],
            ], [], 10);

            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'url' => $this->notificationUrl($user, $data['url'] ?? (string) config('webpush.default_url')),
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE);

            /** @var array<string, PushSubscription> $byEndpoint */
            $byEndpoint = [];

            foreach ($subscriptions as $subscription) {
                $byEndpoint[$subscription->endpoint] = $subscription;

                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding,
                    ]),
                    $payload,
                    ['TTL' => (int) config('webpush.ttl')],
                );
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getEndpoint();
                $subscription = $byEndpoint[$endpoint] ?? null;

                if ($subscription === null) {
                    continue;
                }

                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();
                } elseif ($report->isSuccess()) {
                    $subscription->forceFill(['last_used_at' => now()])->saveQuietly();
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function notificationUrl(User $user, mixed $url): string
    {
        $url = is_string($url) && $url !== '' ? $url : (string) config('webpush.default_url');

        if (
            str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
            || str_starts_with($url, '/api')
            || str_starts_with($url, '/admin')
            || str_starts_with($url, '/auth/aitu')
        ) {
            return $url;
        }

        $locale = LocaleManager::normalize($user->locale) ?? LocaleManager::default();

        return LocaleManager::localizedPath($locale, $url);
    }
}
