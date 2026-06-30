<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for the OTP KZTUSDT WhatsApp API (otp.kztusdt.kz).
 *
 * @see https://otp.kztusdt.kz — API documentation in project README
 */
final class WhatsAppOtpService
{
    public function isConfigured(): bool
    {
        return (string) config('otp.token') !== '';
    }

    /**
     * Send an OTP code to the given phone number via WhatsApp.
     *
     * @return int TTL in seconds reported by the API (default 300).
     */
    public function send(string $phoneE164, ?string $purpose = null): int
    {
        $body = $this->call('otp/send', [
            'phone' => $this->phoneForApi($phoneE164),
            'purpose' => $purpose ?? (string) config('otp.purpose'),
        ]);

        return (int) ($body['expires_in'] ?? config('otp.code_ttl_seconds'));
    }

    public function verify(string $phoneE164, string $code, ?string $purpose = null): void
    {
        $this->call('otp/verify', [
            'phone' => $this->phoneForApi($phoneE164),
            'code' => $code,
            'purpose' => $purpose ?? (string) config('otp.purpose'),
        ]);
    }

    /**
     * E.164 (+77001234567) → API format (77001234567).
     */
    public function phoneForApi(string $phoneE164): string
    {
        $digits = preg_replace('/\D+/', '', $phoneE164) ?? '';

        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $path, array $payload): array
    {
        $response = $this->client()->post($path, $payload);
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('Некорректный ответ сервиса OTP.');
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Сервис OTP: неверный API-токен. Проверьте OTP_API_TOKEN.');
        }

        if (($body['success'] ?? false) !== true) {
            throw new RuntimeException($this->humanizeMessage($body, $response));
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function humanizeMessage(array $body, Response $response): string
    {
        if (isset($body['errors']) && is_array($body['errors'])) {
            $first = collect($body['errors'])->flatten()->first();

            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        $message = (string) ($body['message'] ?? '');

        if ($message !== '') {
            return $message;
        }

        return match ($response->status()) {
            401 => 'Сервис OTP: неверный API-токен.',
            422 => 'Не удалось обработать запрос OTP.',
            default => 'Сервис OTP временно недоступен.',
        };
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $token = (string) config('otp.token');

        if ($token === '') {
            throw new RuntimeException('Авторизация по номеру телефона временно недоступна.');
        }

        return Http::baseUrl(rtrim((string) config('otp.base_url'), '/'))
            ->withToken($token)
            ->acceptJson()
            ->timeout(30);
    }
}
