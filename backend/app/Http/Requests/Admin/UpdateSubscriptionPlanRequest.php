<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateSubscriptionPlanRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'fee_percent' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'timing' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_subscription' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var SubscriptionPlan $plan */
            $plan = $this->route('plan');

            if ($plan->is_default && $this->boolean('is_active') === false) {
                $validator->errors()->add('is_active', 'Базовый тариф нельзя отключить.');
            }
        });
    }
}
