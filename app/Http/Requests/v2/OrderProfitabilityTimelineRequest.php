<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class OrderProfitabilityTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dateFrom'      => 'required|date',
            'dateTo'        => 'required|date|after_or_equal:dateFrom',
            'granularity'   => 'nullable|in:day,week,month',
            'productIds'    => 'nullable|array',
            'productIds.*'  => 'integer|exists:tenant.products,id',
        ];
    }
}
