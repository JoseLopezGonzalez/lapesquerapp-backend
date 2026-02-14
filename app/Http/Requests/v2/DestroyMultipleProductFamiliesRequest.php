<?php

namespace App\Http\Requests\v2;

use App\Models\ProductFamily;
use Illuminate\Foundation\Http\FormRequest;

class DestroyMultipleProductFamiliesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ProductFamily::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'No se han proporcionado IDs válidos.',
            'ids.min' => 'Debe proporcionar al menos un ID válido para eliminar.',
        ];
    }
}
