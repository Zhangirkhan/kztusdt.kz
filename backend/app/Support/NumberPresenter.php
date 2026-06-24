<?php

declare(strict_types=1);

namespace App\Support;

final class NumberPresenter
{
    public static function decimal(float|string $value, int $maxDecimals = 8): string
    {
        $formatted = number_format((float) $value, $maxDecimals, '.', '');

        if (! str_contains($formatted, '.')) {
            return $formatted;
        }

        $formatted = rtrim($formatted, '0');

        return rtrim($formatted, '.');
    }

    public static function withThousands(float|string $value, int $maxDecimals = 2, string $thousandsSep = ' '): string
    {
        $decimal = self::decimal($value, $maxDecimals);
        $parts = explode('.', $decimal);
        $integer = number_format((float) $parts[0], 0, '.', $thousandsSep);

        if (! isset($parts[1]) || $parts[1] === '') {
            return $integer;
        }

        return "{$integer}.{$parts[1]}";
    }

    public static function kzt(float|string $value): string
    {
        return self::withThousands($value, 2);
    }

    public static function usdt(float|string $value, int $maxDecimals = 8): string
    {
        return self::decimal($value, $maxDecimals);
    }

    public static function percent(float|string $value): string
    {
        return self::decimal($value, 4);
    }
}
