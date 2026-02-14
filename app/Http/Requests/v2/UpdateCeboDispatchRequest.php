<?php

namespace App\Http\Requests\v2;

use App\Models\CeboDispatch;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCeboDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dispatch = $this->route('cebo_dispatch');
        if ($dispatch instanceof CeboDispatch) {
            return $this->user()->can('update', $dispatch);
        }
        return $this->user()->can('update', CeboDispatch::findOrFail((int) $dispatch));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier' => 'required|array',
            'supplier.id' => 'required|exists:tenant.suppliers,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'details' => 'required|array|min:1',
            'details.*.product.id' => 'required|exists:tenant.products,id',
            'details.*.netWeight' => 'required|numeric|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier.required' => 'El proveedor es obligatorio.',
            'supplier.id.required' => 'El ID del proveedor es obligatorio.',
            'supplier.id.exists' => 'El proveedor seleccionado no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.date' => 'La fecha no tiene un formato válido.',
            'details.required' => 'Debe incluir al menos un detalle de producto.',
            'details.*.product.id.required' => 'Cada detalle debe tener un producto.',
            'details.*.product.id.exists' => 'Uno de los productos no existe.',
            'details.*.netWeight.required' => 'El peso neto es obligatorio en cada detalle.',
            'details.*.netWeight.numeric' => 'El peso neto debe ser un número.',
            'details.*.netWeight.min' => 'El peso neto debe ser mayor que 0.',
        ];
    }
}
