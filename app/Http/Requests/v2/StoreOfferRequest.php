<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prospectId' => 'nullable|integer|exists:tenant.prospects,id',
            'customerId' => 'nullable|integer|exists:tenant.customers,id',
            'validUntil' => 'nullable|date',
            'incotermId' => 'nullable|integer|exists:tenant.incoterms,id',
            'paymentTermId' => 'nullable|integer|exists:tenant.payment_terms,id',
            'currency' => 'nullable|string|in:EUR,USD',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.productId' => 'nullable|integer|exists:tenant.products,id',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.unit' => 'required|string|max:32',
            'lines.*.unitPrice' => 'required|numeric|min:0',
            'lines.*.taxId' => 'nullable|integer|exists:tenant.taxes,id',
            'lines.*.boxes' => 'nullable|integer|min:1',
            'lines.*.currency' => 'nullable|string|in:EUR,USD',
        ];
    }
}
