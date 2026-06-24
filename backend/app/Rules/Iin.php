<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a Kazakhstan Individual Identification Number (ИИН/IIN).
 *
 * An IIN is a 12-digit string whose final digit is a checksum derived from the
 * preceding 11 digits using two weight vectors (the second is only used when the
 * first produces a remainder of 10).
 */
final class Iin implements ValidationRule
{
    /** @var list<int> */
    private const WEIGHTS_PRIMARY = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

    /** @var list<int> */
    private const WEIGHTS_SECONDARY = [3, 4, 5, 6, 7, 8, 9, 10, 11, 1, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iin = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($iin) !== 12) {
            $fail('ИИН должен содержать 12 цифр.');

            return;
        }

        $digits = array_map('intval', str_split($iin));

        // Birth-date / century-gender sanity: month 01-12, day 01-31, century digit 1-6.
        $month = (int) substr($iin, 2, 2);
        $day = (int) substr($iin, 4, 2);

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $digits[6] < 1 || $digits[6] > 6) {
            $fail('Некорректный ИИН.');

            return;
        }

        if ($this->checksum($digits) !== $digits[11]) {
            $fail('Некорректный ИИН: не совпадает контрольная цифра.');
        }
    }

    /**
     * @param  list<int>  $digits
     */
    private function checksum(array $digits): int
    {
        $control = $this->weightedMod($digits, self::WEIGHTS_PRIMARY);

        if ($control === 10) {
            $control = $this->weightedMod($digits, self::WEIGHTS_SECONDARY);
        }

        return $control;
    }

    /**
     * @param  list<int>  $digits
     * @param  list<int>  $weights
     */
    private function weightedMod(array $digits, array $weights): int
    {
        $sum = 0;

        for ($i = 0; $i < 11; $i++) {
            $sum += $digits[$i] * $weights[$i];
        }

        return $sum % 11;
    }
}
