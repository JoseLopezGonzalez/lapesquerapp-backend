<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionRecord;
use Illuminate\Foundation\Http\FormRequest;

class ProductionRecordOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ProductionRecord::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'production_id' => 'nullable|exists:tenant.productions,id',
            'exclude_id' => 'nullable|integer|exists:tenant.production_records,id',
        ];
    }
}
