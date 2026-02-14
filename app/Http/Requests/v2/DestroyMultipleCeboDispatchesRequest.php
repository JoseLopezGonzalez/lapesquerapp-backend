<?php

namespace App\Http\Requests\v2;

use App\Models\CeboDispatch;
use Illuminate\Foundation\Http\FormRequest;

class DestroyMultipleCeboDispatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CeboDispatch::class);
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
            'ids.required' => 'Debe proporcionar al menos un ID para eliminar.',
            'ids.array' => 'Los IDs deben enviarse como lista.',
            'ids.min' => 'Debe proporcionar al menos un ID válido para eliminar.',
        ];
    }
}
