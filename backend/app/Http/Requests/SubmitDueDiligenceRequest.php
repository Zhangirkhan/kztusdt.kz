<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\DueDiligenceOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitDueDiligenceRequest extends FormRequest
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
        return [
            'source_of_funds' => ['required', 'string', Rule::in(DueDiligenceOptions::sourceOfFunds())],
            'source_of_funds_other' => [
                Rule::requiredIf(fn (): bool => $this->input('source_of_funds') === 'other'),
                'nullable',
                'string',
                'max:255',
            ],
            'occupation' => ['required', 'string', Rule::in(DueDiligenceOptions::occupations())],
            'industry' => ['required', 'string', Rule::in(DueDiligenceOptions::industries())],
            'industry_other' => [
                Rule::requiredIf(fn (): bool => $this->input('industry') === 'other'),
                'nullable',
                'string',
                'max:255',
            ],
            'annual_income' => ['required', 'string', Rule::in(DueDiligenceOptions::annualIncomes())],
            'platform_purpose' => ['required', 'string', Rule::in(DueDiligenceOptions::platformPurposes())],
            'platform_purpose_other' => [
                Rule::requiredIf(fn (): bool => $this->input('platform_purpose') === 'other'),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_of_funds_other.required' => 'Укажите источник происхождения средств.',
            'industry_other.required' => 'Укажите сферу деятельности.',
            'platform_purpose_other.required' => 'Укажите цель использования биржи.',
        ];
    }
}
