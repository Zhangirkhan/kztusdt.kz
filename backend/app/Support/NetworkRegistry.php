<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Thin read-only accessor over config/networks.php.
 *
 * Centralises network metadata lookup (coin type / base path, address format,
 * token decimals, explorer URLs) so services and controllers don't hard-code
 * per-network config keys.
 */
final class NetworkRegistry
{
    /**
     * @return list<string> enabled network codes, in display order
     */
    public static function enabledCodes(): array
    {
        /** @var list<string> $enabled */
        $enabled = (array) config('networks.enabled', []);
        $known = self::all();

        return array_values(array_filter($enabled, static fn (string $code): bool => isset($known[$code])));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        /** @var array<string, array<string, mixed>> $networks */
        $networks = (array) config('networks.networks', []);

        return $networks;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $code): array
    {
        $networks = self::all();

        if (! isset($networks[$code])) {
            throw new RuntimeException("Неизвестная сеть: {$code}.");
        }

        return $networks[$code];
    }

    public static function exists(string $code): bool
    {
        return isset(self::all()[$code]);
    }

    public static function isEnabled(string $code): bool
    {
        return in_array($code, self::enabledCodes(), true);
    }

    public static function basePath(string $code): string
    {
        return (string) self::get($code)['base_path'];
    }

    public static function addressFormat(string $code): string
    {
        return (string) self::get($code)['address_format'];
    }

    public static function asset(string $code): string
    {
        return (string) self::get($code)['asset'];
    }

    public static function decimals(string $code): int
    {
        return (int) self::get($code)['decimals'];
    }

    public static function confirmations(string $code): int
    {
        return (int) self::get($code)['confirmations'];
    }

    public static function explorerTx(string $code): string
    {
        return (string) (self::get($code)['explorer_tx'] ?? '');
    }

    public static function label(string $code): string
    {
        return (string) (self::get($code)['label'] ?? $code);
    }
}
