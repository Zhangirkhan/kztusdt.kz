<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\Iin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserBankCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canUseWallet() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bankCodes = array_keys(config('banks.catalog', []));

        return [
            'iin' => ['required', 'string', new Iin],
            'bank_code' => ['required', 'string', Rule::in($bankCodes)],
            'label' => ['required', 'string', 'max:255'],
            'holder_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'iban' => ['nullable', 'string', 'max:25'],
        ];
    }
}
