<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class GrantSubscriptionRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subscription_plan_id' => [
                'required',
                'integer',
                'exists:subscription_plans,id',
            ],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
