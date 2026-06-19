<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class CloseSupplierLiquidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|integer|exists:tenant.suppliers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reception_ids' => 'present|array',
            'reception_ids.*' => 'integer',
            'dispatch_ids' => 'present|array',
            'dispatch_ids.*' => 'integer',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'El proveedor es obligatorio.',
            'supplier_id.exists' => 'El proveedor indicado no existe.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
            'reception_ids.present' => 'El campo reception_ids es obligatorio.',
            'dispatch_ids.present' => 'El campo dispatch_ids es obligatorio.',
        ];
    }
}
