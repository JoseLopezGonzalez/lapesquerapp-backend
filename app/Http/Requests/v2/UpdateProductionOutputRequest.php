<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionOutputRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'production_record_id' => 'sometimes|exists:tenant.production_records,id',
            'product_id' => 'sometimes|exists:tenant.products,id',
            'lot_id' => 'sometimes|nullable|string',
            'boxes' => 'sometimes|integer|min:0',
            'weight_kg' => 'sometimes|numeric|gt:0',
            'sources' => 'nullable|array',
            'sources.*.source_type' => 'required|in:stock_box,parent_output',
            'sources.*.production_input_id' => 'required_if:sources.*.source_type,stock_box|nullable|exists:tenant.production_inputs,id',
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
                    $hasWeight = !empty($source['contributed_weight_kg']);
                    $hasPercentage = !empty($source['contribution_percentage']);
                    
                    if (!$hasWeight && !$hasPercentage) {
                        $validator->errors()->add(
                            "sources.{$index}",
                            'Se debe especificar O bien contributed_weight_kg O bien contribution_percentage.'
                        );
                    }
                }
                
                // Verificar que la suma de porcentajes sea aproximadamente 100% del CONSUMO REAL
                // Los sources reflejan el consumo real (inputs), no el output final
                $totalPercentage = 0;
                $totalWeight = 0;
                $hasPercentages = false;
                $hasWeights = false;
                
                // Obtener el output y su record para validar
                $outputId = $this->route('id') ?? $this->route('production_output');
                if ($outputId) {
                    $output = \App\Models\ProductionOutput::find($outputId);
                    if ($output && $output->productionRecord) {
                        $record = $output->productionRecord;
                        $totalInputWeight = $record->total_input_weight;
                        
                        foreach ($sources as $index => $source) {
                            if (!empty($source['contribution_percentage'])) {
                                $hasPercentages = true;
                                $totalPercentage += (float) $source['contribution_percentage'];
                            }
                            if (!empty($source['contributed_weight_kg'])) {
                                $hasWeights = true;
                                $totalWeight += (float) $source['contributed_weight_kg'];
                            }
                        }
                        
                        // Validar porcentajes: deben sumar ≈100% del consumo real
                        if ($hasPercentages && abs($totalPercentage - 100) > 0.01) {
                            $validator->errors()->add(
                                'sources',
                                "La suma de contribution_percentage debe ser aproximadamente 100% del consumo real. Suma actual: {$totalPercentage}%"
                            );
                        }
                        
                        // Validar pesos: deben sumar aproximadamente el consumo real
                        if ($hasWeights && $totalInputWeight > 0 && abs($totalWeight - $totalInputWeight) > 0.01) {
                            $validator->errors()->add(
                                'sources',
                                "La suma de contributed_weight_kg debe ser aproximadamente igual al consumo real ({$totalInputWeight}kg). Suma actual: {$totalWeight}kg"
                            );
                        }
                    }
                } else {
                    // Si no hay output_id, solo validar que los porcentajes sumen 100%
                    foreach ($sources as $index => $source) {
                        if (!empty($source['contribution_percentage'])) {
                            $hasPercentages = true;
                            $totalPercentage += (float) $source['contribution_percentage'];
                        }
                    }
                    
                    if ($hasPercentages && abs($totalPercentage - 100) > 0.01) {
                        $validator->errors()->add(
                            'sources',
                            "La suma de contribution_percentage debe ser aproximadamente 100%. Suma actual: {$totalPercentage}%"
                        );
                    }
                }
            }
        });
    }
}

