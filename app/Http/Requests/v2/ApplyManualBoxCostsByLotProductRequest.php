<?php

namespace App\Http\Requests\v2;

use App\Http\Requests\v2\Concerns\AuthorizesCostRegularization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyManualBoxCostsByLotProductRequest extends FormRequest
{
    use AuthorizesCostRegularization;

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['sales', 'stock'])],
            'filters' => 'required|array',
            'filters.dateFrom' => 'required_if:scope,sales|date_format:Y-m-d',
            'filters.dateTo' => 'required_if:scope,sales|date_format:Y-m-d|after_or_equal:filters.dateFrom',
            'filters.productIds' => 'sometimes|array',
            'filters.productIds.*' => 'integer|exists:tenant.products,id',
            'filters.customerIds' => 'sometimes|array',
            'filters.customerIds.*' => 'integer|exists:tenant.customers,id',
            'filters.orderIds' => 'sometimes|array',
            'filters.orderIds.*' => 'integer|exists:tenant.orders,id',
            'filters.storeIds' => 'sometimes|array',
            'filters.storeIds.*' => 'integer|exists:tenant.stores,id',
            'filters.lot' => 'sometimes|nullable|string|max:255',
            'filters.createdFrom' => 'sometimes|date_format:Y-m-d',
            'filters.createdTo' => 'sometimes|date_format:Y-m-d|after_or_equal:filters.createdFrom',
            'lotProductCosts' => 'required|array|min:1',
            'lotProductCosts.*.productId' => 'required|integer|exists:tenant.products,id',
            'lotProductCosts.*.lot' => 'present|nullable|string|max:255',
            'lotProductCosts.*.manualCostPerKg' => 'required|numeric|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $seen = [];

            foreach ($this->input('lotProductCosts', []) as $index => $row) {
                $key = ((int) ($row['productId'] ?? 0)).'|'.($row['lot'] ?? '');

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "lotProductCosts.$index.lot",
                        'La combinacion de producto y lote no puede repetirse.'
                    );
                }

                $seen[$key] = true;
            }
        });
    }
}
