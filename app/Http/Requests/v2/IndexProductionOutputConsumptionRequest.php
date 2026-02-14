<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionOutputConsumption;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductionOutputConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ProductionOutputConsumption::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'production_record_id' => 'nullable|exists:tenant.production_records,id',
            'production_output_id' => 'nullable|exists:tenant.production_outputs,id',
            'production_id' => 'nullable|exists:tenant.productions,id',
            'parent_record_id' => 'nullable|exists:tenant.production_records,id',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
