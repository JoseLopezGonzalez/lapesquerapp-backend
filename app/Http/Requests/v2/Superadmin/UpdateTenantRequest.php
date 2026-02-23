<?php

namespace App\Http\Requests\v2\Superadmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'plan' => 'nullable|string|max:50',
            'renewal_at' => 'nullable|date',
            'timezone' => 'nullable|string|max:50',
            'branding_image_url' => 'nullable|url|max:500',
            'admin_email' => 'nullable|email|max:255',
        ];
    }
}
