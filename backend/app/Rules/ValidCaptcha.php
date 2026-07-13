<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\CaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidCaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CaptchaService::class)->matches(is_string($value) ? $value : null)) {
            $fail('Неверный код с картинки.');
        }
    }
}
