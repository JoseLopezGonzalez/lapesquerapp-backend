<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización se delega al controller via Policy.
    }

    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
