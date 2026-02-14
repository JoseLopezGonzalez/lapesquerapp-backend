<?php

namespace App\Http\Requests\v2;

use App\Models\CostCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CostCatalog::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:tenant.cost_catalog,name',
            'cost_type' => [
                'required',
                Rule::in([
                    CostCatalog::COST_TYPE_PRODUCTION,
                    CostCatalog::COST_TYPE_LABOR,
                    CostCatalog::COST_TYPE_OPERATIONAL,
                    CostCatalog::COST_TYPE_PACKAGING,
                ]),
            ],
            'description' => 'nullable|string',
            'default_unit' => [
                'nullable',
                Rule::in([CostCatalog::DEFAULT_UNIT_TOTAL, CostCatalog::DEFAULT_UNIT_PER_KG]),
            ],
            'is_active' => 'nullable|boolean',
        ];
    }
}
