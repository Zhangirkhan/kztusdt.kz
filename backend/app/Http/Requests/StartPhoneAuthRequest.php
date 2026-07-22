<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClientType;
use App\Rules\Iin;
use App\Rules\ValidCaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartPhoneAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('client_type')) {
            $this->merge(['client_type' => ClientType::Individual->value]);
        }
    }

    public function rules(): array
    {
        $clientType = (string) $this->input('client_type', ClientType::Individual->value);
        $isLegalEntity = $clientType === ClientType::LegalEntity->value;

        $rules = [
            'client_type' => ['required', Rule::in(ClientType::values())],
            'iin' => [Rule::requiredIf(! $isLegalEntity), 'nullable', 'string', new Iin],
            'phone' => ['required', 'string', 'min:10', 'max:50'],
            'ref' => ['nullable', 'string', 'max:16'],
        ];

        if ($this->routeIs('auth.phone.store')) {
            $rules['captcha'] = ['required', 'string', 'max:16', new ValidCaptcha];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'client_type.required' => 'Выберите тип клиента.',
            'iin.required' => 'Введите ИИН.',
            'phone.required' => 'Введите номер телефона.',
            'phone.min' => 'Номер телефона слишком короткий.',
            'captcha.required' => 'Введите код с картинки.',
        ];
    }
}
