<?php

namespace App\Http\Requests\v2;

use App\Http\Requests\v2\Concerns\AuthorizesCostRegularization;
use Illuminate\Foundation\Http\FormRequest;

class MissingStockCostBoxesRequest extends FormRequest
{
    use AuthorizesCostRegularization;

    public function rules(): array
    {
        return [
            'productIds' => 'sometimes|array',
            'productIds.*' => 'integer|exists:tenant.products,id',
            'storeIds' => 'sometimes|array',
            'storeIds.*' => 'integer|exists:tenant.stores,id',
            'lot' => 'sometimes|nullable|string|max:255',
            'createdFrom' => 'sometimes|date_format:Y-m-d',
            'createdTo' => 'sometimes|date_format:Y-m-d|after_or_equal:createdFrom',
        ];
    }
}
