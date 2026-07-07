<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminManualKycRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->route('user');
        $isLegalEntity = $user->isLegalEntity();

        return [
            'company_name' => [Rule::requiredIf($isLegalEntity), 'nullable', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'document_type' => ['nullable', 'in:id_card,passport,registration'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
