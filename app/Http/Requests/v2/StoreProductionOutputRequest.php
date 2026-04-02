<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionOutput;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOutputRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductionOutput::class);
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
            'product_id' => 'required|exists:tenant.products,id',
            'lot_id' => 'nullable|string',
            'boxes' => 'required|integer|min:0',
            'weight_kg' => 'required|numeric|gt:0',
            'sources' => 'nullable|array',
            'sources.*.source_type' => 'required|in:stock_product,parent_output',
            'sources.*.product_id' => 'required_if:sources.*.source_type,stock_product|nullable|exists:tenant.products,id|prohibited_if:sources.*.source_type,parent_output',
            'sources.*.production_input_id' => 'prohibited',
            'sources.*.production_output_consumption_id' => 'required_if:sources.*.source_type,parent_output|nullable|exists:tenant.production_output_consumptions,id',
            'sources.*.contributed_weight_kg' => 'nullable|numeric|min:0',
            'sources.*.contribution_percentage' => 'nullable|numeric|min:0|max:100',
            'sources.*.contributed_boxes' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sources = $this->input('sources');
            
            if ($sources && is_array($sources) && count($sources) > 0) {
                // Verificar que cada source tenga O bien peso O bien porcentaje
                foreach ($sources as $index => $source) {
                    $hasWeight = array_key_exists('contributed_weight_kg', $source)
                        && $source['contributed_weight_kg'] !== null
                        && $source['contributed_weight_kg'] !== '';
                    $hasPercentage = array_key_exists('contribution_percentage', $source)
                        && $source['contribution_percentage'] !== null
                        && $source['contribution_percentage'] !== '';
                    
                    if (!$hasWeight && !$hasPercentage) {
                        $validator->errors()->add(
                            "sources.{$index}",
                            'Se debe especificar O bien contributed_weight_kg O bien contribution_percentage.'
                        );
                    }

                    if (($source['source_type'] ?? null) === 'stock_product' && !empty($source['product_id']) && $recordId = $this->input('production_record_id')) {
                        $productExistsInInputs = \App\Models\ProductionInput::query()
                            ->where('production_record_id', $recordId)
                            ->whereHas('box', fn ($query) => $query->where('article_id', $source['product_id']))
                            ->exists();

                        if (! $productExistsInInputs) {
                            $validator->errors()->add(
                                "sources.{$index}.product_id",
                                'El product_id de una source stock_product debe existir entre los inputs del proceso.'
                            );
                        }
                    }
                }
            }
        });
    }
}
