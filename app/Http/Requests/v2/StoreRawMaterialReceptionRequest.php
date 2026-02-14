<?php

namespace App\Http\Requests\v2;

use App\Models\RawMaterialReception;
use Illuminate\Foundation\Http\FormRequest;

class StoreRawMaterialReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RawMaterialReception::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier.id' => 'required|exists:tenant.suppliers,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'declaredTotalAmount' => 'nullable|numeric|min:0',
            'declaredTotalNetWeight' => 'nullable|numeric|min:0',
            'details' => 'required_without:pallets|array',
            'details.*.product.id' => 'required_with:details|exists:tenant.products,id',
            'details.*.netWeight' => 'required_with:details|numeric|min:0',
            'details.*.price' => 'nullable|numeric|min:0',
            'details.*.lot' => 'nullable|string|max:255',
            'details.*.boxes' => 'nullable|integer|min:0',
            'pallets' => 'required_without:details|array',
            'pallets.*.observations' => 'nullable|string|max:1000',
            'pallets.*.store.id' => 'nullable|integer|exists:tenant.stores,id',
            'pallets.*.boxes' => 'required_with:pallets|array|min:1',
            'pallets.*.boxes.*.product.id' => 'required|exists:tenant.products,id',
            'pallets.*.boxes.*.lot' => 'nullable|string|max:255',
            'pallets.*.boxes.*.gs1128' => 'required|string|max:255',
            'pallets.*.boxes.*.grossWeight' => 'required|numeric|min:0',
            'pallets.*.boxes.*.netWeight' => 'required|numeric|min:0.01',
            'prices' => 'required_with:pallets|array',
            'prices.*.product.id' => 'required_with:prices|exists:tenant.products,id',
            'prices.*.lot' => 'required_with:prices|string|max:255',
            'prices.*.price' => 'required_with:prices|numeric|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier.id.required' => 'El proveedor es obligatorio.',
            'supplier.id.exists' => 'El proveedor seleccionado no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'details.required_without' => 'Debe indicar detalles (líneas) o palets.',
            'pallets.required_without' => 'Debe indicar detalles (líneas) o palets.',
        ];
    }
}
