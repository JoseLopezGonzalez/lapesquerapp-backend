<?php

namespace App\Http\Requests\v2;

use App\Models\RawMaterialReception;
use Illuminate\Foundation\Http\FormRequest;

class DailyCalibersBySpeciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', RawMaterialReception::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'speciesId' => 'required|integer|exists:tenant.species,id',
        ];
    }
}
