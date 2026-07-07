<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\Bin;
use Illuminate\Foundation\Http\FormRequest;

final class StartLegalEntityEdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:10', 'max:50'],
            'bin' => ['required', 'string', new Bin],
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }
}
