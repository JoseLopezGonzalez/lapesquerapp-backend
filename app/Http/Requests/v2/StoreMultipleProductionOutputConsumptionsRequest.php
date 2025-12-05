<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreMultipleProductionOutputConsumptionsRequest extends FormRequest
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
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'consumptions' => 'required|array|min:1',
            'consumptions.*.production_output_id' => 'required|exists:tenant.production_outputs,id',
            'consumptions.*.consumed_weight_kg' => 'required|numeric|min:0',
            'consumptions.*.consumed_boxes' => 'nullable|integer|min:0',
            'consumptions.*.notes' => 'nullable|string',
        ];
    }
}

