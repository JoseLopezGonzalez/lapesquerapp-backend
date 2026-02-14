<?php

namespace App\Http\Requests\v2;

use App\Models\ProductFamily;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $family = $this->route('product_family');
        if ($family instanceof ProductFamily) {
            return $this->user()->can('update', $family);
        }
        if (! $family) {
            return false;
        }
        return $this->user()->can('update', ProductFamily::findOrFail($family));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('product_family');
        $id = $id instanceof ProductFamily ? $id->id : $id;

        return [
            'name' => 'sometimes|required|string|min:3|max:255|unique:tenant.product_families,name,' . $id,
            'description' => 'nullable|string|max:1000',
            'categoryId' => 'sometimes|required|exists:tenant.product_categories,id',
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

    /**
     * Datos validados para actualización (categoryId → category_id).
     *
     * @return array<string, mixed>
     */
    public function getUpdateData(): array
    {
        $v = $this->validated();
        $data = [];
        if (array_key_exists('name', $v)) {
            $data['name'] = $v['name'];
        }
        if (array_key_exists('description', $v)) {
            $data['description'] = $v['description'];
        }
        if (array_key_exists('active', $v)) {
            $data['active'] = $v['active'];
        }
        if (array_key_exists('categoryId', $v)) {
            $data['category_id'] = $v['categoryId'];
        }
        return $data;
    }
}
