<?php

namespace App\Http\Requests\v2;

use App\Http\Requests\v2\Concerns\AuthorizesCostRegularization;
use Illuminate\Foundation\Http\FormRequest;

class MissingSalesCostBoxesRequest extends FormRequest
{
    use AuthorizesCostRegularization;

    public function rules(): array
    {
        return [
            'dateFrom' => 'required|date_format:Y-m-d',
            'dateTo' => 'required|date_format:Y-m-d|after_or_equal:dateFrom',
            'productIds' => 'sometimes|array',
            'productIds.*' => 'integer|exists:tenant.products,id',
            'customerIds' => 'sometimes|array',
            'customerIds.*' => 'integer|exists:tenant.customers,id',
            'orderIds' => 'sometimes|array',
            'orderIds.*' => 'integer|exists:tenant.orders,id',
        ];
    }
}
