<?php

declare(strict_types=1);

namespace App\Support;

final class KazakhstanAccount
{
    public const IBAN_LENGTH = 20;

    public static function normalizeIban(?string $iban): ?string
    {
        if ($iban === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');

        if ($normalized === '' || $normalized === 'KZ') {
            return null;
        }

        return $normalized;
    }

    public static function isValidIban(?string $iban): bool
    {
        $normalized = self::normalizeIban($iban);

        if ($normalized === null) {
            return false;
        }

        return (bool) preg_match('/^KZ[0-9A-Z]{18}$/', $normalized);
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '' || $digits === '7') {
            return null;
        }

        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '7')) {
            $digits = '7'.$digits;
        }

        $digits = substr($digits, 0, 11);

        if (strlen($digits) !== 11) {
            return null;
        }

        return '+'.$digits;
    }

    public static function isValidPhone(?string $phone): bool
    {
        $normalized = self::normalizePhone($phone);

        if ($normalized === null) {
            return false;
        }

        $digits = substr($normalized, 1);

        return (bool) preg_match('/^7(700|701|702|705|706|707|708|747|771|775|776|777|778)\d{7}$/', $digits);
    }
}
