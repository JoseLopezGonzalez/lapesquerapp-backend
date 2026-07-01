<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class AuxiliaryLineChartDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
            'groupBy' => 'nullable|string|in:day,week,month',
        ];
    }
}
