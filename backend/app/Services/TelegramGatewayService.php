<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Telegram Gateway API (gatewayapi.telegram.org).
 *
 * The Gateway delivers one-time verification codes straight to the Telegram
 * account tied to a phone number — no bot interaction required.
 *
 * @see https://core.telegram.org/gateway/api
 */
final class TelegramGatewayService
{
    public function isConfigured(): bool
    {
        return ! empty(config('telegram.gateway.token'));
    }

    /**
     * Send a verification code to the given phone number.
     *
     * @return string The Gateway request_id used later to track/verify the code.
     */
    public function sendVerificationMessage(string $phone, string $code, ?string $payload = null): string
    {
        $result = $this->call('sendVerificationMessage', array_filter([
            'phone_number' => $phone,
            'code' => $code,
            'sender_username' => config('telegram.gateway.sender_username'),
            'ttl' => config('telegram.gateway.code_ttl_seconds'),
            'payload' => $payload,
        ], static fn ($value): bool => $value !== null && $value !== ''));

        $requestId = $result['request_id'] ?? null;

        if (! is_string($requestId) || $requestId === '') {
            throw new RuntimeException('Не удалось отправить код через Telegram.');
        }

        return $requestId;
    }

    /**
     * Report the entered code back to Telegram for conversion tracking.
     *
     * Best-effort only: the authoritative check is done locally against the
     * stored hash, so failures here must never block the login flow.
     */
    public function reportVerificationStatus(string $requestId, string $code): void
    {
        try {
            $this->call('checkVerificationStatus', [
                'request_id' => $requestId,
                'code' => $code,
            ]);
        } catch (RuntimeException) {
            // Tracking is non-critical; ignore Gateway errors.
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function call(string $method, array $parameters): array
    {
        $response = $this->client()->post($method, $parameters);

        $body = $response->json();

        if (! is_array($body) || ($body['ok'] ?? false) !== true) {
            $error = is_array($body) ? ($body['error'] ?? 'UNKNOWN_ERROR') : 'INVALID_RESPONSE';

            throw new RuntimeException("Telegram Gateway error: {$error}");
        }

        $result = $body['result'] ?? [];

        return is_array($result) ? $result : [];
    }

    private function client(): PendingRequest
    {
        $token = (string) config('telegram.gateway.token');

        if ($token === '') {
            throw new RuntimeException('Telegram Gateway не настроен.');
        }

        return Http::baseUrl(rtrim((string) config('telegram.gateway.base_url'), '/'))
            ->withToken($token)
            ->acceptJson()
            ->timeout(15);
    }
}
