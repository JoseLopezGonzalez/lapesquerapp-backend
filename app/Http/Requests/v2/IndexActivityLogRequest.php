<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class IndexActivityLogRequest extends FormRequest
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
            'users' => 'nullable|array',
            'users.*' => 'integer',
            'ipAddresses' => 'nullable|array',
            'ipAddresses.*' => 'string',
            'countries' => 'nullable|array',
            'countries.*' => 'string',
            'city' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:500',
            'dates' => 'nullable|array',
            'dates.start' => 'nullable|string',
            'dates.end' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
