<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionInput;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductionInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ProductionInput::class);
    }

    public function rules(): array
    {
        return [
            'production_record_id' => 'nullable|exists:tenant.production_records,id',
            'box_id' => 'nullable|exists:tenant.boxes,id',
            'production_id' => 'nullable|exists:tenant.productions,id',
        ];
    }
}
