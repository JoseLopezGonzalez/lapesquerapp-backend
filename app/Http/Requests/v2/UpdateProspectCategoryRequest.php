<?php

namespace App\Http\Requests\v2;

use App\Models\ProspectCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProspectCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('prospect_category');
        if ($category instanceof ProspectCategory) {
            return $this->user()->can('update', $category);
        }
        if (! $category) {
            return false;
        }

        return $this->user()->can('update', ProspectCategory::findOrFail($category));
    }

    public function rules(): array
    {
        $id = $this->route('prospect_category');
        $id = $id instanceof ProspectCategory ? $id->id : $id;

        return [
            'name' => 'sometimes|required|string|min:3|max:255|unique:tenant.prospect_categories,name,'.$id,
            'description' => 'nullable|string|max:1000',
            'active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una categoría de prospecto con este nombre.',
        ];
    }
}
