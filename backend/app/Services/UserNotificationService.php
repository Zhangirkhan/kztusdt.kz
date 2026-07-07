<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\LocaleManager;

/**
 * Single entry point for user-facing notifications.
 *
 * Historically these went to a Telegram bot; they are now delivered as PWA
 * Web Push notifications. The legacy `notifyUser(User, string $message)` shape
 * is preserved so call sites stay unchanged: the HTML/multiline message is
 * split into a push title (first line) and body (the rest).
 */
final class UserNotificationService
{
    public function __construct(
        private readonly PushNotificationService $pushService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(User $user, string $message, array $data = []): void
    {
        [$title, $body] = $this->splitMessage($message);

        $this->pushService->sendToUser($user, $title, $body, $data);
    }

    /**
     * @param  array<string, mixed>  $replace
     * @param  array<string, mixed>  $data
     */
    public function notifyKey(User $user, string $key, array $replace = [], array $data = []): void
    {
        $locale = LocaleManager::normalize($user->locale) ?? LocaleManager::default();
        $message = trans("notifications.{$key}", $replace, $locale);

        if (! is_string($message) || $message === "notifications.{$key}") {
            $message = trans("notifications.{$key}", $replace, LocaleManager::default());
        }

        $this->notifyUser($user, (string) $message, $data);
    }

    /**
     * @return array{0:string,1:string} [title, body]
     */
    private function splitMessage(string $message): array
    {
        $plain = $this->toPlainText($message);
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $plain)),
            static fn (string $line): bool => $line !== '',
        ));

        if ($lines === []) {
            return [(string) config('company.name', 'kztusdt.kz'), ''];
        }

        $title = array_shift($lines);
        $body = trim(implode("\n", $lines));

        if ($body === '') {
            return [(string) config('company.name', 'kztusdt.kz'), $title];
        }

        return [$title, $body];
    }

    private function toPlainText(string $message): string
    {
        $withoutTags = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));

        return trim(html_entity_decode($withoutTags, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
