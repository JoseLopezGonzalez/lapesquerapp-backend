<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionInput;
use Illuminate\Foundation\Http\FormRequest;

class SyncMultipleProductionInputsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductionInput::class);
    }

    public function rules(): array
    {
        return [
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'box_ids' => 'required|array',
            'box_ids.*' => 'required|integer|distinct|exists:tenant.boxes,id',
        ];
    }

    public function messages(): array
    {
        return [
            'production_record_id.required' => 'Debe indicar el proceso de producción.',
            'production_record_id.exists' => 'El proceso de producción indicado no existe.',
            'box_ids.required' => 'Debe enviar la lista final de cajas.',
            'box_ids.array' => 'La lista de cajas debe ser un array.',
            'box_ids.*.distinct' => 'La lista de cajas contiene valores duplicados.',
        ];
    }
}
