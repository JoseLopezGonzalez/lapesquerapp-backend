<?php

namespace App\Http\Requests\v2;

use App\Models\AuxiliaryProduct;
use Illuminate\Foundation\Http\FormRequest;

class StoreAuxiliaryProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AuxiliaryProduct::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:tenant.auxiliary_products,name',
            'reference' => 'nullable|string|max:100',
            'unit' => 'required|string|max:50',
            'defaultPrice' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'active' => 'nullable|boolean',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        return [
            'name' => $validated['name'],
            'reference' => $validated['reference'] ?? null,
            'unit' => $validated['unit'],
            'default_price' => $validated['defaultPrice'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => $validated['active'] ?? true,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un artículo auxiliar con este nombre.',
        ];
    }
}
