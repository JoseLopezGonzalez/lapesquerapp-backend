<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class SyncProductionOutputsRequest extends FormRequest
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
            // `present` allows explicit empty array (`outputs: []`) for full sync delete intent.
            'outputs' => 'present|array',
            'outputs.*.id' => 'sometimes|nullable|integer|exists:tenant.production_outputs,id',
            'outputs.*.product_id' => 'required|exists:tenant.products,id',
            'outputs.*.lot_id' => 'nullable|string',
            'outputs.*.boxes' => 'required|integer|min:0',
            'outputs.*.weight_kg' => 'required|numeric|gt:0',
            'outputs.*.sources' => 'sometimes|nullable|array',
            'outputs.*.sources.*.source_type' => 'required|in:stock_product,parent_output',
            'outputs.*.sources.*.product_id' => 'required_if:outputs.*.sources.*.source_type,stock_product|nullable|exists:tenant.products,id|prohibited_if:outputs.*.sources.*.source_type,parent_output',
            'outputs.*.sources.*.production_input_id' => 'prohibited',
            'outputs.*.sources.*.production_output_consumption_id' => 'required_if:outputs.*.sources.*.source_type,parent_output|nullable|exists:tenant.production_output_consumptions,id',
            'outputs.*.sources.*.contributed_weight_kg' => 'nullable|numeric|min:0',
            'outputs.*.sources.*.contribution_percentage' => 'nullable|numeric|min:0',
            'outputs.*.sources.*.contributed_boxes' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $outputs = $this->input('outputs', []);
            $recordId = $this->route('id') ?? $this->route('production_record');
            foreach ($outputs as $outputIndex => $output) {
                if (! array_key_exists('sources', $output) || ! is_array($output['sources']) || count($output['sources']) === 0) {
                    continue;
                }

                $sources = $output['sources'];

                foreach ($sources as $sourceIndex => $source) {
                    $hasWeight = array_key_exists('contributed_weight_kg', $source)
                        && $source['contributed_weight_kg'] !== null
                        && $source['contributed_weight_kg'] !== '';
                    $hasPercentage = array_key_exists('contribution_percentage', $source)
                        && $source['contribution_percentage'] !== null
                        && $source['contribution_percentage'] !== '';

                    if (! $hasWeight && ! $hasPercentage) {
                        $validator->errors()->add(
                            "outputs.{$outputIndex}.sources.{$sourceIndex}",
                            'Se debe especificar O bien contributed_weight_kg O bien contribution_percentage.'
                        );
                    }

                    if (($source['source_type'] ?? null) === 'stock_product' && ! empty($source['product_id']) && $recordId) {
                        $productExistsInInputs = \App\Models\ProductionInput::query()
                            ->where('production_record_id', $recordId)
                            ->whereHas('box', fn ($query) => $query->where('article_id', $source['product_id']))
                            ->exists();

                        if (! $productExistsInInputs) {
                            $validator->errors()->add(
                                "outputs.{$outputIndex}.sources.{$sourceIndex}.product_id",
                                'El product_id de una source stock_product debe existir entre los inputs del proceso.'
                            );
                        }
                    }
                }
            }
        });
    }
}
