<?php

namespace App\Http\Requests\v2;

use App\Http\Requests\v2\Concerns\ValidatesProfitabilityDateRange;
use Illuminate\Foundation\Http\FormRequest;

class OrderProfitabilityTimelineRequest extends FormRequest
{
    use ValidatesProfitabilityDateRange;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dateFrom' => 'required|date',
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom', $this->maxRangeRule()],
            'granularity' => 'nullable|in:day,week,month',
            'productIds' => 'nullable|array',
            'productIds.*' => 'integer|exists:tenant.products,id',
        ];
    }
}
