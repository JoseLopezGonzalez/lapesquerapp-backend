<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionRequest extends FormRequest
{
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
        $productionId = $this->route('id');

        return [
            'lot' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tenant.productions', 'lot')->ignore($productionId),
            ],
            'species_id' => 'sometimes|nullable|exists:tenant.species,id',
            'capture_zone_id' => 'sometimes|nullable|exists:tenant.capture_zones,id',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}

