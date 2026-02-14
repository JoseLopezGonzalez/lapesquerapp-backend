<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderPlannedProductDetailRequest extends FormRequest
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
            'orderId' => 'required|integer|exists:tenant.orders,id',
            'boxes' => 'required|integer',
            'product.id' => 'required|integer|exists:tenant.products,id',
            'quantity' => 'required|numeric',
            'tax.id' => 'required|integer|exists:tenant.taxes,id',
            'unitPrice' => 'required|numeric',
        ];
    }
}
