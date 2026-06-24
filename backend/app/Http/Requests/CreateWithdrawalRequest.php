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

        return [
            'network' => ['nullable', 'string', Rule::in(NetworkRegistry::enabledCodes())],
            'to_address' => ['required', 'string', $addressRule],
            'amount' => ['required', 'numeric', 'min:0.000001'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_address.regex' => $this->isTron()
                ? 'Адрес TRON должен начинаться с T и состоять из 34 символов.'
                : 'Адрес должен быть в формате 0x + 40 hex-символов.',
        ];
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
