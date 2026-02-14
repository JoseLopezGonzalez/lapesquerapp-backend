<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'buyerReference' => 'sometimes|nullable|string',
            'payment' => 'sometimes|integer|exists:tenant.payment_terms,id',
            'billingAddress' => 'sometimes|string',
            'shippingAddress' => 'sometimes|string',
            'transportationNotes' => 'sometimes|nullable|string',
            'productionNotes' => 'sometimes|nullable|string',
            'accountingNotes' => 'sometimes|nullable|string',
            'salesperson' => 'sometimes|integer|exists:tenant.salespeople,id',
            'emails' => 'sometimes|nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'sometimes|nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
            'transport' => 'sometimes|integer|exists:tenant.transports,id',
            'entryDate' => 'sometimes|date',
            'loadDate' => 'sometimes|date',
            'status' => 'sometimes|string|in:pending,finished,incident',
            'incoterm' => 'sometimes|integer|exists:tenant.incoterms,id',
            'truckPlate' => 'sometimes|nullable|string',
            'trailerPlate' => 'sometimes|nullable|string',
            'temperature' => 'sometimes|nullable|numeric',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'buyerReference.string' => 'La referencia del comprador debe ser texto.',
            'payment.integer' => 'El término de pago debe ser un número entero.',
            'payment.exists' => 'El término de pago seleccionado no existe.',
            'billingAddress.string' => 'La dirección de facturación debe ser texto.',
            'shippingAddress.string' => 'La dirección de envío debe ser texto.',
            'transportationNotes.string' => 'Las notas de transporte deben ser texto.',
            'productionNotes.string' => 'Las notas de producción deben ser texto.',
            'accountingNotes.string' => 'Las notas contables deben ser texto.',
            'salesperson.integer' => 'El comercial debe ser un número entero.',
            'salesperson.exists' => 'El comercial seleccionado no existe.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.string' => 'Cada email debe ser texto.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.string' => 'Cada email en copia debe ser texto.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
            'transport.integer' => 'El transporte debe ser un número entero.',
            'transport.exists' => 'El transporte seleccionado no existe.',
            'entryDate.date' => 'La fecha de entrada debe ser una fecha válida.',
            'loadDate.date' => 'La fecha de carga debe ser una fecha válida.',
            'status.string' => 'El estado debe ser texto.',
            'status.in' => 'El estado del pedido no es válido. Valores permitidos: pending, finished, incident.',
            'incoterm.integer' => 'El incoterm debe ser un número entero.',
            'incoterm.exists' => 'El incoterm seleccionado no existe.',
            'truckPlate.string' => 'La matrícula del camión debe ser texto.',
            'trailerPlate.string' => 'La matrícula del remolque debe ser texto.',
            'temperature.numeric' => 'La temperatura debe ser un número.',
        ];
    }
}
