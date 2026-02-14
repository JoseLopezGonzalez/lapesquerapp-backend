<?php

namespace App\Http\Requests\v2;

use App\Models\ProductionInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductionInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductionInput::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'box_id' => 'required|exists:tenant.boxes,id',
        ];
    }
}

