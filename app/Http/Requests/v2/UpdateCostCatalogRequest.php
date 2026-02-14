<?php

namespace App\Http\Requests\v2;

use App\Models\CostCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCostCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cost = $this->route('cost_catalog');
        $id = $cost ? (is_object($cost) ? $cost->id : $cost) : $this->route('id');
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('tenant.cost_catalog', 'name')->ignore($id),
            ],
            'cost_type' => [
                'sometimes',
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
