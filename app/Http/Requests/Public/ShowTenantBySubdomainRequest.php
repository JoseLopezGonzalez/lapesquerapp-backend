<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class ShowTenantBySubdomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => $this->route('subdomain'),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subdomain' => 'required|string|max:63|alpha_dash',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.required' => 'El subdominio es obligatorio.',
            'subdomain.max' => 'El subdominio no puede tener más de 63 caracteres.',
            'subdomain.alpha_dash' => 'El subdominio solo puede contener letras, números, guiones y guiones bajos.',
        ];
    }
}
