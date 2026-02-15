<?php

namespace App\Http\Requests\v2;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class ExtractPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole(Role::values());
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pdf.required' => 'Debe enviar un archivo PDF.',
            'pdf.file' => 'El archivo no es válido.',
            'pdf.mimes' => 'El archivo debe ser un PDF.',
            'pdf.max' => 'El archivo PDF no puede superar 20 MB.',
        ];
    }
}
