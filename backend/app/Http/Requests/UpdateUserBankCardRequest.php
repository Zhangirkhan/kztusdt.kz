<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserBankCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $card = $this->route('card');

        return ($this->user()?->canUseWallet() ?? false)
            && $card !== null
            && (int) $card->user_id === (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bankCodes = array_keys(config('banks.catalog', []));

        return [
            'bank_code' => ['sometimes', 'required', 'string', Rule::in($bankCodes)],
            'bik' => ['sometimes', 'required', 'string', 'size:8', 'regex:/^[A-Za-z0-9]{8}$/'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'holder_name' => ['sometimes', 'required', 'string', 'max:255'],
            'iban' => ['sometimes', 'required', 'string', 'max:25'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
