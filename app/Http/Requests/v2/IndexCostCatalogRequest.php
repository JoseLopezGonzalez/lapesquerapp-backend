<?php

namespace App\Http\Requests\v2;

use App\Models\CostCatalog;
use Illuminate\Foundation\Http\FormRequest;

class IndexCostCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CostCatalog::class);
    }

    public function rules(): array
    {
        return [
            'cost_type' => 'nullable|string|in:production,labor,operational,packaging',
            'active_only' => 'nullable|boolean',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
