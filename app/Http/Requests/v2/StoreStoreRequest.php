<?php

namespace App\Http\Requests\v2;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Store::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255|unique:tenant.stores,name',
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
