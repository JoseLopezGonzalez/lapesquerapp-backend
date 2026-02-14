<?php

namespace App\Http\Requests\v2;

use App\Models\CeboDispatch;
use Illuminate\Foundation\Http\FormRequest;

class DispatchChartDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CeboDispatch::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
            'speciesId' => 'nullable|integer|exists:tenant.species,id',
            'familyId' => 'nullable|integer|exists:tenant.product_families,id',
            'categoryId' => 'nullable|integer|exists:tenant.product_categories,id',
            'valueType' => 'required|in:amount,quantity',
            'groupBy' => 'nullable|in:day,week,month',
        ];
    }
}
