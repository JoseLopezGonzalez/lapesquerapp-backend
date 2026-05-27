<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class DestroyMultipleSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:tenant.personal_access_tokens,id',
        ];
    }
}
