<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\KazakhstanMobilePhone;
use App\Services\PhoneAuthService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => app(PhoneAuthService::class)->normalizePhone((string) $this->input('phone')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => [
                'required',
                'string',
                'max:50',
                new KazakhstanMobilePhone(),
                Rule::unique(User::class, 'phone')->ignore($this->user()->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('profile.validation.name_required'),
            'email.email' => __('profile.validation.email_invalid'),
            'email.unique' => __('profile.validation.email_unique'),
            'phone.required' => __('profile.validation.phone_required'),
            'phone.unique' => __('profile.validation.phone_unique'),
        ];
    }
}
