<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderAuxiliaryLineRequest extends FormRequest
{
    /**
     * La autorización se resuelve en el controlador contra el pedido (Order::update).
     */
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
            'auxiliaryProductId' => 'nullable|integer|exists:tenant.auxiliary_products,id',
            'description' => 'required_without:auxiliaryProductId|nullable|string|max:500',
            'quantity' => 'required|numeric|gt:0',
            'unit' => 'required|string|max:50',
            'unitPrice' => 'required|numeric|min:0',
            'taxId' => 'nullable|integer|exists:tenant.taxes,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required_without' => 'Se requiere un artículo del catálogo o una descripción libre.',
            'quantity.gt' => 'La cantidad debe ser mayor que cero.',
            'unitPrice.min' => 'El precio unitario no puede ser negativo.',
        ];
    }
}
