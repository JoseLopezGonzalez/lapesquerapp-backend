<?php

namespace App\Http\Requests\v2;

use App\Models\RawMaterialReception;
use Illuminate\Foundation\Http\FormRequest;

class IndexRawMaterialReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', RawMaterialReception::class);
    }

    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'suppliers' => 'nullable|array',
            'suppliers.*' => 'integer',
            'dates' => 'nullable|array',
            'dates.start' => 'nullable|date',
            'dates.end' => 'nullable|date',
            'species' => 'nullable|array',
            'species.*' => 'integer',
            'products' => 'nullable|array',
            'products.*' => 'integer',
            'notes' => 'nullable|string|max:1000',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
