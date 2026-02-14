<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Email rule: rfc,dns in production; only rfc in testing (DNS often unavailable in CI).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $emailRule = app()->environment('testing') ? 'string|email:rfc|distinct' : 'string|email:rfc,dns|distinct';

        return [
            'name' => 'required|string|max:255',
            'vatNumber' => 'nullable|string|max:20',
            'billing_address' => 'nullable|string|max:1000',
            'shipping_address' => 'nullable|string|max:1000',
            'transportation_notes' => 'nullable|string|max:1000',
            'production_notes' => 'nullable|string|max:1000',
            'accounting_notes' => 'nullable|string|max:1000',
            'emails' => 'nullable|array',
            'emails.*' => $emailRule,
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => $emailRule,
            'contact_info' => 'nullable|string|max:1000',
            'salesperson_id' => 'nullable|exists:tenant.salespeople,id',
            'country_id' => 'nullable|exists:tenant.countries,id',
            'payment_term_id' => 'nullable|exists:tenant.payment_terms,id',
            'transport_id' => 'nullable|exists:tenant.transports,id',
            'a3erp_code' => 'nullable|string|max:255',
            'facilcom_code' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del cliente es obligatorio.',
            'name.string' => 'El nombre del cliente debe ser texto.',
            'name.max' => 'El nombre del cliente no puede tener más de 255 caracteres.',
            'vatNumber.string' => 'El NIF/CIF debe ser texto.',
            'vatNumber.max' => 'El NIF/CIF no puede tener más de 20 caracteres.',
            'billing_address.string' => 'La dirección de facturación debe ser texto.',
            'billing_address.max' => 'La dirección de facturación no puede tener más de 1000 caracteres.',
            'shipping_address.string' => 'La dirección de envío debe ser texto.',
            'shipping_address.max' => 'La dirección de envío no puede tener más de 1000 caracteres.',
            'transportation_notes.string' => 'Las notas de transporte deben ser texto.',
            'transportation_notes.max' => 'Las notas de transporte no pueden tener más de 1000 caracteres.',
            'production_notes.string' => 'Las notas de producción deben ser texto.',
            'production_notes.max' => 'Las notas de producción no pueden tener más de 1000 caracteres.',
            'accounting_notes.string' => 'Las notas contables deben ser texto.',
            'accounting_notes.max' => 'Las notas contables no pueden tener más de 1000 caracteres.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.string' => 'Cada email debe ser texto.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.string' => 'Cada email en copia debe ser texto.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
            'contact_info.string' => 'La información de contacto debe ser texto.',
            'contact_info.max' => 'La información de contacto no puede tener más de 1000 caracteres.',
            'salesperson_id.exists' => 'El comercial seleccionado no existe.',
            'country_id.exists' => 'El país seleccionado no existe.',
            'payment_term_id.exists' => 'El término de pago seleccionado no existe.',
            'transport_id.exists' => 'El transporte seleccionado no existe.',
            'a3erp_code.string' => 'El código A3ERP debe ser texto.',
            'a3erp_code.max' => 'El código A3ERP no puede tener más de 255 caracteres.',
            'facilcom_code.string' => 'El código Facilcom debe ser texto.',
            'facilcom_code.max' => 'El código Facilcom no puede tener más de 255 caracteres.',
        ];
    }
}
