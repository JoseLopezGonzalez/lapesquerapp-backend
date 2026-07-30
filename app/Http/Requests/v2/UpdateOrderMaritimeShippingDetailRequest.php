<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderMaritimeShippingDetailRequest extends FormRequest
{
    /**
     * La autorización se resuelve en el controlador contra el pedido (Order::update).
     */
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
            'vesselName' => 'nullable|string|max:255',
            'voyageNumber' => 'nullable|string|max:100',
            'exportInvoiceNumber' => 'nullable|string|max:100',
            'bookingNumber' => 'nullable|string|max:100',
            'swbNumber' => 'nullable|string|max:100',
            'loadingPort' => 'nullable|string|max:255',
            'dischargePort' => 'nullable|string|max:255',
            'originCountry' => 'nullable|string|max:255',
            'destinationCountry' => 'nullable|string|max:255',
            'customsBrokerId' => 'nullable|exists:tenant.customs_brokers,id',
            'ultimateConsigneeName' => 'nullable|string|max:255',
            'ultimateConsigneeAddress' => 'nullable|string|max:1000',
        ];
    }
}
