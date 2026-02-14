<?php

namespace App\Http\Requests\v2;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', Setting::class);
    }

    /**
     * Acepta un objeto/array de clave-valor. Sin whitelist estricta para no romper clientes.
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            '*' => 'nullable',
        ];
    }
}
