<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class IndexSupplierLiquidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'suppliers' => 'nullable|array',
            'suppliers.*' => 'integer',
            'dates' => 'nullable|array',
            'dates.start' => 'nullable|date',
            'dates.end' => 'nullable|date',
            'closed_at' => 'nullable|array',
            'closed_at.start' => 'nullable|date',
            'closed_at.end' => 'nullable|date',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
