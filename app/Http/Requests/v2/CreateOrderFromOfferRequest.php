<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderFromOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entryDate' => 'required|date',
            'loadDate' => 'required|date',
            'transport' => 'nullable|integer|exists:tenant.transports,id',
            'buyerReference' => 'nullable|string|max:255',
            'billingAddress' => 'nullable|string',
            'shippingAddress' => 'nullable|string',
            'transportationNotes' => 'nullable|string',
            'productionNotes' => 'nullable|string',
            'accountingNotes' => 'nullable|string',
            'emails' => 'nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
            'plannedProducts' => 'nullable|array',
            'plannedProducts.*.product' => 'required_with:plannedProducts|integer|exists:tenant.products,id',
            'plannedProducts.*.quantity' => 'required_with:plannedProducts|numeric|min:0.001',
            'plannedProducts.*.boxes' => 'required_with:plannedProducts|integer|min:1',
            'plannedProducts.*.unitPrice' => 'required_with:plannedProducts|numeric|min:0',
            'plannedProducts.*.tax' => 'required_with:plannedProducts|integer|exists:tenant.taxes,id',
        ];
    }
}
