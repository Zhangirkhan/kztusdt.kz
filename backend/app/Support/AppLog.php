<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

final class AppLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function auth(string $message, array $context = []): void
    {
        self::write('auth', 'info', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function authWarning(string $message, array $context = []): void
    {
        self::write('auth', 'warning', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function http(string $message, array $context = [], string $level = 'info'): void
    {
        self::write('http', $level, $message, $context);
    }

    /**
     * Structured operational log (deposits, sweeps, withdrawals, rates…).
     *
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = [], string $channel = 'daily'): void
    {
        self::write($channel, 'info', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = [], string $channel = 'daily'): void
    {
        self::write($channel, 'warning', $message, $context);
    }

    public static function exception(Throwable $exception, array $context = []): void
    {
        self::write('errors', 'error', class_basename($exception).': '.$exception->getMessage(), [
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            ...$context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $channel, string $level, string $message, array $context = []): void
    {
        try {
            Log::channel($channel)->log($level, $message, self::context($context));
        } catch (Throwable) {
            // Logging must never break auth or HTTP responses.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function context(array $context): array
    {
        return array_filter([
            'request_id' => RequestLogContext::id(),
            ...$context,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
