<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyPhoneAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $length = (int) config('telegram.gateway.code_length', 6);

        return [
            'code' => ['required', 'string', 'regex:/^\d{4,8}$/', 'size:'.$length],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Введите код подтверждения.',
            'code.regex' => 'Код должен содержать только цифры.',
            'code.size' => 'Неверная длина кода.',
        ];
    }
}
