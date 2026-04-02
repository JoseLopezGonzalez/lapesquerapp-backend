<?php

namespace App\Http\Requests\v2;

use App\Models\Prospect;
use Illuminate\Foundation\Http\FormRequest;

class ConvertToCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vatNumber'           => 'nullable|string|max:255',
            'billingAddress'      => 'nullable|string',
            'shippingAddress'     => 'nullable|string',
            'transportId'         => 'nullable|integer|exists:tenant.transports,id',
            'paymentTermId'       => 'nullable|integer|exists:tenant.payment_terms,id',
            'a3erpCode'           => 'nullable|string|max:255',
            'facilcomCode'        => 'nullable|string|max:255',
            'transportationNotes' => 'nullable|string',
            'productionNotes'     => 'nullable|string',
            'accountingNotes'     => 'nullable|string',
        ];
    }
}
