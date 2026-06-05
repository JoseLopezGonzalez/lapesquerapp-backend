<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class GenerateClosedLiquidationPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => 'nullable|string|in:cash,transfer',
            'has_management_fee' => 'nullable|boolean',
            'show_transfer_payment' => 'nullable|boolean',
        ];
    }
}
