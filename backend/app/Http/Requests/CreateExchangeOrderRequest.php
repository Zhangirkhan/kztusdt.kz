<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'listing_id' => ['nullable', 'integer', 'exists:exchange_listings,id'],
        ];

        if ($direction === 'buy') {
            $rules['kzt_amount'] = ['required_without:usdt_amount', 'nullable', 'numeric', 'min:0.01'];
            $rules['usdt_amount'] = ['required_without:kzt_amount', 'nullable', 'numeric', 'min:0.000001'];
            $rules['payment_bank_code'] = ['nullable', 'string', 'max:32'];
        }

        if ($direction === 'sell') {
            $rules['usdt_amount'] = ['required', 'numeric', 'min:0.000001'];
            $rules['card_id'] = [
                'required',
                'integer',
                Rule::exists('user_bank_cards', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ];
            $rules['payout_type'] = ['required', 'string', 'in:phone,iban'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_id.required' => 'Выберите карту для получения KZT.',
            'card_id.exists' => 'Карта не найдена. Добавьте реквизиты в профиле.',
            'payout_type.required' => 'Выберите способ получения KZT.',
            'payout_type.in' => 'Способ получения должен быть телефон или IBAN.',
            'payment_bank_code.required' => 'Выберите банк для оплаты.',
        ];
    }
}
