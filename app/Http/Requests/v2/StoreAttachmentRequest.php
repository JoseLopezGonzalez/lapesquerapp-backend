<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización se delega al controller via Policy.
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'], // 20 MB límite HTTP; el servicio aplica el límite por colección.
            'collection' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo es obligatorio.',
            'file.file' => 'El campo debe ser un archivo válido.',
            'file.max' => 'El archivo no puede superar 20 MB.',
            'collection.required' => 'La colección es obligatoria.',
        ];
    }
}
