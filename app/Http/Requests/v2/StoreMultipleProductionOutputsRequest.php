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
            'outputs' => 'required|array|min:1',
            'outputs.*.product_id' => 'required|exists:tenant.products,id',
            'outputs.*.lot_id' => 'nullable|string',
            'outputs.*.boxes' => 'required|integer|min:0',
            'outputs.*.weight_kg' => 'required|numeric|min:0',
        ];
    }
}

