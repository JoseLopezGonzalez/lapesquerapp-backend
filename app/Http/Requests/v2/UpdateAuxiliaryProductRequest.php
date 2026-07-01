<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuxiliaryProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('auxiliary_product'));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('auxiliary_product')?->getKey();

        return [
            'name' => 'sometimes|required|string|max:255|unique:tenant.auxiliary_products,name,'.$id,
            'reference' => 'nullable|string|max:100',
            'unit' => 'sometimes|required|string|max:50',
            'defaultPrice' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'active' => 'nullable|boolean',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        $data = [];

        if (array_key_exists('name', $validated)) {
            $data['name'] = $validated['name'];
        }
        if (array_key_exists('reference', $validated)) {
            $data['reference'] = $validated['reference'];
        }
        if (array_key_exists('unit', $validated)) {
            $data['unit'] = $validated['unit'];
        }
        if (array_key_exists('defaultPrice', $validated)) {
            $data['default_price'] = $validated['defaultPrice'];
        }
        if (array_key_exists('notes', $validated)) {
            $data['notes'] = $validated['notes'];
        }
        if (array_key_exists('active', $validated)) {
            $data['active'] = $validated['active'];
        }

        return $data;
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
