<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\NetworkRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateWithdrawalRequest extends FormRequest
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
        $addressRule = $this->isTron()
            ? 'regex:/^T[1-9A-HJ-NP-Za-km-z]{33}$/'
            : 'regex:/^0x[0-9a-fA-F]{40}$/';

        $minAmount = (float) config('withdrawal.min_amount', 1);

        return [
            'network' => ['nullable', 'string', Rule::in(NetworkRegistry::enabledCodes())],
            'to_address' => ['required', 'string', 'max:64', $addressRule],
            'amount' => ['required', 'numeric', 'min:'.$minAmount, 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $minAmount = (float) config('withdrawal.min_amount', 1);

        return [
            'to_address.regex' => $this->isTron()
                ? 'Адрес TRON должен начинаться с T и состоять из 34 символов.'
                : 'Адрес должен быть в формате 0x + 40 hex-символов.',
            'amount.min' => "Минимальная сумма вывода — {$minAmount} USDT.",
            'amount.decimal' => 'Сумма должна быть с точностью не более 2 знаков после запятой.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('to_address');

        if (is_string($address)) {
            $this->merge([
                'to_address' => preg_replace('/\s+/', '', $address) ?? $address,
            ]);
        }

        $amount = $this->input('amount');

        if (is_string($amount)) {
            $this->merge([
                'amount' => str_replace(',', '.', trim($amount)),
            ]);
        }
    }

    public function resolvedNetwork(): string
    {
        $network = (string) $this->input('network', '');

        return NetworkRegistry::isEnabled($network) ? $network : (string) config('wallet.network');
    }

    private function isTron(): bool
    {
        return NetworkRegistry::exists($this->resolvedNetwork())
            && NetworkRegistry::addressFormat($this->resolvedNetwork()) === 'tron';
    }
}
