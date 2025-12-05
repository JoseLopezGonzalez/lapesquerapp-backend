<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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

