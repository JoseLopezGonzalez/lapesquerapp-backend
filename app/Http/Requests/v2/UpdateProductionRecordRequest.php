<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionRecordRequest extends FormRequest
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
            'production_id' => 'sometimes|exists:tenant.productions,id',
            'parent_record_id' => 'sometimes|nullable|exists:tenant.production_records,id',
            'process_id' => 'sometimes|required|exists:tenant.processes,id',
            'started_at' => 'sometimes|nullable|date',
            'finished_at' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}

