<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReferralBenefitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminReferralBenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'super_admin',
            'super_admin_manager',
            'security_officer',
        ]) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ReferralBenefitType::values())],
            'value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
