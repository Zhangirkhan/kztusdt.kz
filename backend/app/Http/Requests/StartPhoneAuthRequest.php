<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClientType;
use App\Rules\Bin;
use App\Rules\Iin;
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

        return [
            'client_type' => ['required', Rule::in(ClientType::values())],
            'iin' => [Rule::requiredIf(! $isLegalEntity), 'nullable', 'string', new Iin],
            'bin' => [Rule::requiredIf($isLegalEntity), 'nullable', 'string', new Bin],
            'company_name' => [Rule::requiredIf($isLegalEntity), 'nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_type.required' => 'Выберите тип клиента.',
            'iin.required' => 'Введите ИИН.',
            'bin.required' => 'Введите БИН.',
            'company_name.required' => 'Введите наименование организации.',
            'phone.required' => 'Введите номер телефона.',
            'phone.min' => 'Номер телефона слишком короткий.',
        ];
    }
}
