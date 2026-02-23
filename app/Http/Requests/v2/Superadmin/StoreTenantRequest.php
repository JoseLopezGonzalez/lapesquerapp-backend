<?php

namespace App\Http\Requests\v2\Superadmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/',
                'unique:mysql.tenants,subdomain',
            ],
            'admin_email' => 'required|email|max:255',
            'plan' => 'nullable|string|max:50',
            'timezone' => 'nullable|string|max:50',
            'branding_image_url' => 'nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'subdomain.regex' => 'El subdominio solo puede contener letras minúsculas, números y guiones, y no puede empezar/terminar con guión.',
            'subdomain.unique' => 'El subdominio ya está en uso.',
        ];
    }
}
