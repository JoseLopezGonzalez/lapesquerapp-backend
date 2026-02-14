<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabelRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:tenant.labels,name',
            'format' => 'nullable|array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la etiqueta es obligatorio.',
            'name.string' => 'El nombre de la etiqueta debe ser texto.',
            'name.max' => 'El nombre de la etiqueta no puede tener más de 255 caracteres.',
            'name.unique' => 'Ya existe una etiqueta con este nombre.',
            'format.array' => 'El formato debe ser un objeto o array.',
        ];
    }
}
