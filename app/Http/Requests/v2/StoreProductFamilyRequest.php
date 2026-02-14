<?php

namespace App\Http\Requests\v2;

use App\Models\ProductFamily;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductFamily::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255|unique:tenant.product_families,name',
            'description' => 'nullable|string|max:1000',
            'categoryId' => 'required|exists:tenant.product_categories,id',
            'active' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una familia de producto con este nombre.',
        ];
    }
}
