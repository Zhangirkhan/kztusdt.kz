<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a Kazakhstan Business Identification Number (БИН/BIN) for legal entities.
 */
final class Bin implements ValidationRule
{
    /** @var list<int> */
    private const WEIGHTS_PRIMARY = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

    /** @var list<int> */
    private const WEIGHTS_SECONDARY = [3, 4, 5, 6, 7, 8, 9, 10, 11, 1, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $bin = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($bin) !== 12) {
            $fail('БИН должен содержать 12 цифр.');

            return;
        }

        $digits = array_map('intval', str_split($bin));
        $month = (int) substr($bin, 2, 2);
        $day = (int) substr($bin, 4, 2);

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            $fail('Некорректный БИН.');

            return;
        }

        if ($this->checksum($digits) !== $digits[11]) {
            $fail('Некорректный БИН: не совпадает контрольная цифра.');
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
