<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionRequest extends FormRequest
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
            'lot' => 'nullable|string|max:255',
            'species_id' => 'nullable|exists:tenant.species,id',
            'capture_zone_id' => 'nullable|exists:tenant.capture_zones,id',
            'notes' => 'nullable|string',
        ];
    }
}

