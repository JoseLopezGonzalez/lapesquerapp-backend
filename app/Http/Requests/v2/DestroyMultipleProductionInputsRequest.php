<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionInput;
use Illuminate\Foundation\Http\FormRequest;

class DestroyMultipleProductionInputsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ProductionInput::class);
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Debe proporcionar al menos un ID para eliminar.',
            'ids.array' => 'Los IDs deben enviarse como lista.',
            'ids.min' => 'Debe proporcionar al menos un ID válido para eliminar.',
        ];
    }
}
