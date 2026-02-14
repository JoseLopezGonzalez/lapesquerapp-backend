<?php

namespace App\Http\Requests\v2;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('product_category');
        if ($category instanceof ProductCategory) {
            return $this->user()->can('update', $category);
        }
        if (! $category) {
            return false;
        }
        return $this->user()->can('update', ProductCategory::findOrFail($category));
    }

    public function rules(): array
    {
        $id = $this->route('product_category');
        $id = $id instanceof ProductCategory ? $id->id : $id;

        return [
            'name' => 'sometimes|required|string|min:3|max:255|unique:tenant.product_categories,name,' . $id,
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
