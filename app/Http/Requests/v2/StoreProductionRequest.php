<?php

namespace App\Http\Requests\v2;

use App\Models\Production;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Production::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lot' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tenant.productions', 'lot'),
            ],
            'species_id' => 'nullable|exists:tenant.species,id',
            'capture_zone_id' => 'nullable|exists:tenant.capture_zones,id',
            'notes' => 'nullable|string',
        ];
    }
}

