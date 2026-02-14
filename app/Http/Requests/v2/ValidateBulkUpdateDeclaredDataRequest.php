<?php

namespace App\Http\Requests\v2;

use App\Models\RawMaterialReception;
use Illuminate\Foundation\Http\FormRequest;

class ValidateBulkUpdateDeclaredDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', RawMaterialReception::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receptions' => 'required|array|min:1',
            'receptions.*.supplier_id' => 'required|integer|exists:tenant.suppliers,id',
            'receptions.*.date' => 'required|date',
            'receptions.*.declared_total_amount' => 'nullable|numeric|min:0',
            'receptions.*.declared_total_net_weight' => 'nullable|numeric|min:0',
        ];
    }
}
