<?php

namespace App\Http\Requests\v2;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255|unique:tenant.product_categories,name',
            'description' => 'nullable|string|max:1000',
            'active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una categoría de producto con este nombre.',
        ];
    }
}
