<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateInvoicedOrdersRequest extends FormRequest
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
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.orders,id',
            'invoiced' => 'required|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Debe proporcionar al menos un ID válido.',
            'ids.min' => 'Debe proporcionar al menos un ID válido.',
            'ids.*.integer' => 'Los IDs deben ser números enteros.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
            'invoiced.required' => 'Debe indicar el nuevo estado de facturación.',
            'invoiced.boolean' => 'El estado de facturación debe ser verdadero o falso.',
        ];
    }
}
