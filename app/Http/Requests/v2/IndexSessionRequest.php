<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class IndexSessionRequest extends FormRequest
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
            'user_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
