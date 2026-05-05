<?php

namespace App\Http\Requests\v2;

use App\Http\Requests\v2\Concerns\AuthorizesCostRegularization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyManualBoxCostsByProductRequest extends FormRequest
{
    use AuthorizesCostRegularization;

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (! $this->has('filters')) {
            $filters = [];

            foreach (['dateFrom', 'dateTo', 'productIds', 'customerIds', 'orderIds', 'storeIds', 'lot', 'createdFrom', 'createdTo'] as $field) {
                if ($this->has($field)) {
                    $filters[$field] = $this->input($field);
                }
            }

            $normalized['filters'] = $filters;
        }

        if (! $this->has('productCosts') && $this->has('products')) {
            $normalized['productCosts'] = $this->input('products');
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['sales', 'stock'])],
            'filters' => 'array',
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
            'productCosts' => 'required|array|min:1',
            'productCosts.*.productId' => 'required|integer|distinct|exists:tenant.products,id',
            'productCosts.*.manualCostPerKg' => 'required|numeric|min:0',
        ];
    }
}
