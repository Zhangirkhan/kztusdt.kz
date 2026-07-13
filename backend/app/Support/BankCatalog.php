<?php

declare(strict_types=1);

namespace App\Support;

final class BankCatalog
{
    /**
     * @return array<string, array|string>
     */
    public static function entries(): array
    {
        return (array) config('banks.catalog', []);
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::entries());
    }

    public static function nameForCode(string $code): string
    {
        $entry = self::entries()[$code] ?? null;

        if (is_array($entry)) {
            return (string) ($entry['name'] ?? $code);
        }

        if (is_string($entry) && $entry !== '') {
            return $entry;
        }

        return $code;
    }

    public static function bikForCode(string $code): ?string
    {
        $entry = self::entries()[$code] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        $bik = strtoupper(trim((string) ($entry['bik'] ?? '')));

        return $bik !== '' ? $bik : null;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function options(): array
    {
        return collect(self::entries())
            ->map(fn (array|string $entry, string $code): array => [
                'code' => $code,
                'name' => self::nameForCode($code),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, name: string, bik: string|null}>
     */
    public static function optionsWithBik(): array
    {
        return collect(self::entries())
            ->map(fn (array|string $entry, string $code): array => [
                'code' => $code,
                'name' => self::nameForCode($code),
                'bik' => self::bikForCode($code),
            ])
            ->values()
            ->all();
    }
}
