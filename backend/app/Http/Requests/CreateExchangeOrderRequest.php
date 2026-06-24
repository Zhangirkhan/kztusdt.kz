<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateExchangeOrderRequest extends FormRequest
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
        $direction = $this->input('direction');

        $rules = [
            'direction' => ['required', 'in:buy,sell'],
        ];

        if ($direction === 'buy') {
            $rules['kzt_amount'] = ['required_without:usdt_amount', 'nullable', 'numeric', 'min:0.01'];
            $rules['usdt_amount'] = ['required_without:kzt_amount', 'nullable', 'numeric', 'min:0.000001'];
        }

        if ($direction === 'sell') {
            $rules['usdt_amount'] = ['required', 'numeric', 'min:0.000001'];
            $rules['bank_name'] = ['required', 'string', 'max:255'];
            $rules['recipient_name'] = ['required', 'string', 'max:255'];
            $rules['recipient_account'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }
}
