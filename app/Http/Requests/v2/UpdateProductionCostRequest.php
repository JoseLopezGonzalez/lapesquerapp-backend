<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionCost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cost_catalog_id' => 'nullable|exists:tenant.cost_catalog,id',
            'cost_type' => [
                'sometimes',
                Rule::in([
                    ProductionCost::COST_TYPE_PRODUCTION,
                    ProductionCost::COST_TYPE_LABOR,
                    ProductionCost::COST_TYPE_OPERATIONAL,
                    ProductionCost::COST_TYPE_PACKAGING,
                ]),
            ],
            'name' => 'sometimes|nullable|string|max:255',
            'description' => 'nullable|string',
            'total_cost' => 'nullable|numeric|min:0',
            'cost_per_kg' => 'nullable|numeric|min:0',
            'distribution_unit' => 'nullable|string',
            'cost_date' => 'nullable|date',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tc = $this->input('total_cost');
            $cpk = $this->input('cost_per_kg');
            $hasTc = $tc !== null && $tc !== '';
            $hasCpk = $cpk !== null && $cpk !== '';
            if ($hasTc && $hasCpk) {
                $validator->errors()->add('total_cost', 'Solo uno de total_cost o cost_per_kg debe estar presente.');
            }
        });
    }
}
