<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'document_type' => ['required', 'in:id_card,passport'],
            'document_number' => ['required', 'string', 'max:100'],
            'id_front' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5120)],
            'id_back' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5120)],
            'selfie' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5120)],
        ];
    }
}
