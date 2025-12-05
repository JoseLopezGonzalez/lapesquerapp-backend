<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOutputRequest extends FormRequest
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
            'product_id' => 'required|exists:tenant.products,id',
            'lot_id' => 'nullable|string',
            'boxes' => 'required|integer|min:0',
            'weight_kg' => 'required|numeric|gt:0',
        ];
    }
}

