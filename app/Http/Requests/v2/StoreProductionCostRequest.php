<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionCost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductionCost::class);
    }

    public function rules(): array
    {
        return [
            'production_record_id' => 'nullable|exists:tenant.production_records,id',
            'production_id' => 'nullable|exists:tenant.productions,id',
            'cost_catalog_id' => 'nullable|exists:tenant.cost_catalog,id',
            'cost_type' => [
                'required_without:cost_catalog_id',
                Rule::in([
                    ProductionCost::COST_TYPE_PRODUCTION,
                    ProductionCost::COST_TYPE_LABOR,
                    ProductionCost::COST_TYPE_OPERATIONAL,
                    ProductionCost::COST_TYPE_PACKAGING,
                ]),
            ],
            'name' => 'required_without:cost_catalog_id|nullable|string|max:255',
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
            $pr = $this->input('production_record_id');
            $pi = $this->input('production_id');
            if (empty($pr) && empty($pi)) {
                $validator->errors()->add('production_record_id', 'Debe especificarse production_record_id o production_id.');
                return;
            }
            if (!empty($pr) && !empty($pi)) {
                $validator->errors()->add('production_record_id', 'Solo uno de production_record_id o production_id debe estar presente.');
                return;
            }
            $tc = $this->input('total_cost');
            $cpk = $this->input('cost_per_kg');
            $hasTc = $tc !== null && $tc !== '';
            $hasCpk = $cpk !== null && $cpk !== '';
            if (!$hasTc && !$hasCpk) {
                $validator->errors()->add('total_cost', 'Se debe especificar O bien total_cost O bien cost_per_kg.');
                return;
            }
            if ($hasTc && $hasCpk) {
                $validator->errors()->add('total_cost', 'Solo uno de total_cost o cost_per_kg debe estar presente.');
            }
        });
    }
}
