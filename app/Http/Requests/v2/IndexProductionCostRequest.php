<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionCost;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductionCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ProductionCost::class);
    }

    public function rules(): array
    {
        return [
            'production_record_id' => 'nullable|exists:tenant.production_records,id',
            'production_id' => 'nullable|exists:tenant.productions,id',
            'cost_type' => 'nullable|string|in:production,labor,operational,packaging',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
