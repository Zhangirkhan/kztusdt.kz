<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RenameUserBankCardRequest extends FormRequest
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
        return [
            'label' => ['required', 'string', 'max:255'],
        ];
    }
}
