<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\ExchangeListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $priceType = $this->input('price_type');
        $banks = array_keys((array) config('banks.catalog', []));
        $terms = array_keys((array) config('exchange_listings.payment_terms', []));

        return [
            'direction' => ['required', Rule::in([
                ExchangeListing::DIRECTION_SELL_USDT,
                ExchangeListing::DIRECTION_BUY_USDT,
            ])],
            'price_type' => ['required', Rule::in([
                ExchangeListing::PRICE_FIXED,
                ExchangeListing::PRICE_FLOATING,
            ])],
            'fixed_rate' => [
                Rule::requiredIf($priceType === ExchangeListing::PRICE_FIXED),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'margin_percent' => [
                Rule::requiredIf($priceType === ExchangeListing::PRICE_FLOATING),
                'nullable',
                'numeric',
                'min:-50',
                'max:100',
            ],
            'total_usdt' => ['required', 'numeric', 'min:0.000001'],
            'min_limit_kzt' => ['required', 'numeric', 'min:5000'],
            'max_limit_kzt' => ['required', 'numeric', 'min:1'],
            'payment_methods' => ['required', 'array', 'min:1', 'max:5'],
            'payment_methods.*' => ['required', 'string', Rule::in($banks)],
            'payment_term' => ['required', 'string', Rule::in($terms)],
            'conditions_text' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'publish' => ['sometimes', 'boolean'],
        ];
    }
}
