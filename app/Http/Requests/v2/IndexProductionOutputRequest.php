<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionOutput;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductionOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ProductionOutput::class);
    }

    public function rules(): array
    {
        return [
            'production_record_id' => 'nullable|exists:tenant.production_records,id',
            'product_id' => 'nullable|exists:tenant.products,id',
            'lot_id' => 'nullable|string|max:255',
            'production_id' => 'nullable|exists:tenant.productions,id',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
