<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreMultipleProductionOutputsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\ProductionOutput::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'outputs' => 'required|array|min:1',
            'outputs.*.product_id' => 'required|exists:tenant.products,id',
            'outputs.*.lot_id' => 'nullable|string',
            'outputs.*.boxes' => 'required|integer|min:0',
            'outputs.*.weight_kg' => 'required|numeric|gt:0',
            'outputs.*.sources' => 'nullable|array',
            'outputs.*.sources.*.source_type' => 'required|in:stock_box,parent_output',
            'outputs.*.sources.*.production_input_id' => 'required_if:outputs.*.sources.*.source_type,stock_box|nullable|exists:tenant.production_inputs,id',
            'outputs.*.sources.*.production_output_consumption_id' => 'required_if:outputs.*.sources.*.source_type,parent_output|nullable|exists:tenant.production_output_consumptions,id',
            'outputs.*.sources.*.contributed_weight_kg' => 'nullable|numeric|min:0',
            'outputs.*.sources.*.contribution_percentage' => 'nullable|numeric|min:0|max:100',
            'outputs.*.sources.*.contributed_boxes' => 'nullable|integer|min:0',
        ];
    }
}

