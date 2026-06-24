<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', Rule::unique('subscription_plans', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'timing' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_subscription' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Код тарифа: только латиница, цифры, дефис и подчёркивание.',
            'code.unique' => 'Тариф с таким кодом уже существует.',
        ];
    }
}
