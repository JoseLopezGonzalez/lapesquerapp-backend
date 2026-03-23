<?php

namespace App\Http\Requests\v2\Superadmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:ip,email',
            'value' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date',
        ];
    }
}
