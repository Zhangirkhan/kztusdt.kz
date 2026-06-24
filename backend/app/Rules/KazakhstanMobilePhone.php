<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class KazakhstanMobilePhone implements ValidationRule
{
    /** @var list<string> */
    private const PREFIXES = [
        '700', '701', '702', '705', '706', '707', '708',
        '747', '771', '775', '776', '777', '778',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        if (strlen($digits) !== 11 || ! str_starts_with($digits, '7')) {
            $fail('Введите казахстанский номер полностью: +7 (707) 123-45-67');

            return;
        }

        if (! in_array(substr($digits, 1, 3), self::PREFIXES, true)) {
            $fail('Неверный код оператора. Допустимы: 700–708, 747, 771, 775–778.');
        }
    }
}
