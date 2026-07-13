<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\Iin;
use Illuminate\Foundation\Http\FormRequest;

final class ConfirmKycIinRequest extends FormRequest
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
            'iin' => ['required', 'string', new Iin],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'iin.required' => 'Введите ИИН.',
        ];
    }
}
