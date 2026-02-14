<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionRecord;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductionRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductionRecord::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'production_id' => 'required|exists:tenant.productions,id',
            'parent_record_id' => 'nullable|exists:tenant.production_records,id',
            'process_id' => 'required|exists:tenant.processes,id',
            'started_at' => 'nullable|date',
            'finished_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}

