<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\Iin;
use Illuminate\Foundation\Http\FormRequest;

final class StartPhoneAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'iin' => ['required', 'string', new Iin],
            'phone' => ['required', 'string', 'min:10', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'iin.required' => 'Введите ИИН.',
            'phone.required' => 'Введите номер телефона.',
            'phone.min' => 'Номер телефона слишком короткий.',
        ];
    }
}
