<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam customer integer required ID del cliente (tenant). Example: 1
 * @bodyParam entryDate string required Fecha de entrada (Y-m-d). Example: 2025-02-14
 * @bodyParam loadDate string required Fecha de carga (Y-m-d). Example: 2025-02-15
 * @bodyParam salesperson integer ID del comercial. No-example
 * @bodyParam payment integer ID de condición de pago. No-example
 * @bodyParam incoterm integer ID de incoterm. No-example
 * @bodyParam buyerReference string Referencia del comprador. No-example
 * @bodyParam plannedProducts array Líneas planificadas. No-example
 * @bodyParam plannedProducts.*.product integer required ID del producto. Example: 1
 * @bodyParam plannedProducts.*.quantity number required Cantidad. Example: 100
 * @bodyParam plannedProducts.*.boxes integer required Número de cajas. Example: 10
 * @bodyParam plannedProducts.*.unitPrice number required Precio unitario. Example: 5.50
 * @bodyParam plannedProducts.*.tax integer required ID del impuesto. Example: 1
 */
class StoreOrderRequest extends FormRequest
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
            'customer' => 'required|integer|exists:tenant.customers,id',
            'entryDate' => 'required|date',
            'loadDate' => 'required|date',
            'salesperson' => 'nullable|integer|exists:tenant.salespeople,id',
            'payment' => 'nullable|integer|exists:tenant.payment_terms,id',
            'incoterm' => 'nullable|integer|exists:tenant.incoterms,id',
            'buyerReference' => 'nullable|string',
            'transport' => 'nullable|integer|exists:tenant.transports,id',
            'truckPlate' => 'nullable|string',
            'trailerPlate' => 'nullable|string',
            'temperature' => 'nullable|string',
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
            'plannedProducts.*.product' => 'required|integer|exists:tenant.products,id',
            'plannedProducts.*.quantity' => 'required|numeric',
            'plannedProducts.*.boxes' => 'required|integer',
            'plannedProducts.*.unitPrice' => 'required|numeric',
            'plannedProducts.*.tax' => 'required|integer|exists:tenant.taxes,id',
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
            'customer.required' => 'El cliente es obligatorio.',
            'customer.integer' => 'El cliente debe ser un número entero.',
            'customer.exists' => 'El cliente seleccionado no existe.',
            'entryDate.required' => 'La fecha de entrada es obligatoria.',
            'entryDate.date' => 'La fecha de entrada debe ser una fecha válida.',
            'loadDate.required' => 'La fecha de carga es obligatoria.',
            'loadDate.date' => 'La fecha de carga debe ser una fecha válida.',
            'salesperson.integer' => 'El comercial debe ser un número entero.',
            'salesperson.exists' => 'El comercial seleccionado no existe.',
            'payment.integer' => 'El término de pago debe ser un número entero.',
            'payment.exists' => 'El término de pago seleccionado no existe.',
            'incoterm.integer' => 'El incoterm debe ser un número entero.',
            'incoterm.exists' => 'El incoterm seleccionado no existe.',
            'buyerReference.string' => 'La referencia del comprador debe ser texto.',
            'transport.integer' => 'El transporte debe ser un número entero.',
            'transport.exists' => 'El transporte seleccionado no existe.',
            'truckPlate.string' => 'La matrícula del camión debe ser texto.',
            'trailerPlate.string' => 'La matrícula del remolque debe ser texto.',
            'temperature.string' => 'La temperatura debe ser texto.',
            'billingAddress.string' => 'La dirección de facturación debe ser texto.',
            'shippingAddress.string' => 'La dirección de envío debe ser texto.',
            'transportationNotes.string' => 'Las notas de transporte deben ser texto.',
            'productionNotes.string' => 'Las notas de producción deben ser texto.',
            'accountingNotes.string' => 'Las notas contables deben ser texto.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.string' => 'Cada email debe ser texto.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.string' => 'Cada email en copia debe ser texto.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
            'plannedProducts.array' => 'Los productos planificados deben ser una lista.',
            'plannedProducts.*.product.required' => 'El producto es obligatorio en cada línea.',
            'plannedProducts.*.product.integer' => 'El producto debe ser un número entero.',
            'plannedProducts.*.product.exists' => 'Uno o más productos seleccionados no existen.',
            'plannedProducts.*.quantity.required' => 'La cantidad es obligatoria en cada línea.',
            'plannedProducts.*.quantity.numeric' => 'La cantidad debe ser un número.',
            'plannedProducts.*.boxes.required' => 'El número de cajas es obligatorio en cada línea.',
            'plannedProducts.*.boxes.integer' => 'El número de cajas debe ser un número entero.',
            'plannedProducts.*.unitPrice.required' => 'El precio unitario es obligatorio en cada línea.',
            'plannedProducts.*.unitPrice.numeric' => 'El precio unitario debe ser un número.',
            'plannedProducts.*.tax.required' => 'El impuesto es obligatorio en cada línea.',
            'plannedProducts.*.tax.integer' => 'El impuesto debe ser un número entero.',
            'plannedProducts.*.tax.exists' => 'Uno o más impuestos seleccionados no existen.',
        ];
    }

    /**
     * Configure the validator (entry_date ≤ load_date).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('entryDate') && $this->filled('loadDate') && $this->entryDate > $this->loadDate) {
                $validator->errors()->add('loadDate', 'La fecha de carga debe ser mayor o igual a la fecha de entrada.');
            }
        });
    }
}
