<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionRecord;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductionRecordRequest extends FormRequest
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
            'root_only' => 'nullable|boolean',
            'parent_record_id' => 'nullable|exists:tenant.production_records,id',
            'process_id' => 'nullable|exists:tenant.processes,id',
            'completed' => 'nullable|string|in:true,false',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
