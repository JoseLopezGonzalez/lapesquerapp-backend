<?php

namespace App\Http\Requests\v2;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('store');
        if ($id instanceof Store) {
            return $this->user()->can('update', $id);
        }
        if (!$id) {
            return false;
        }
        return $this->user()->can('update', Store::findOrFail($id));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('store');
        $storeId = $id instanceof Store ? $id->id : $id;

        return [
            'name' => 'required|string|min:3|max:255|unique:tenant.stores,name,' . $storeId,
            'temperature' => 'required|numeric|between:-99.99,99.99',
            'capacity' => 'required|numeric|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del almacén es obligatorio.',
            'name.unique' => 'Ya existe un almacén con este nombre.',
            'temperature.required' => 'La temperatura es obligatoria.',
            'temperature.between' => 'La temperatura debe estar entre -99.99 y 99.99.',
            'capacity.required' => 'La capacidad es obligatoria.',
        ];
    }
}
