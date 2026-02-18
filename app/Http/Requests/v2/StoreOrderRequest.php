<?php

namespace App\Http\Requests\v2;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam orderType string Tipo de pedido: 'standard' o 'autoventa'. No-example
 * @bodyParam customer integer required ID del cliente (tenant). Example: 1
 * @bodyParam entryDate string required Fecha de entrada (Y-m-d). Example: 2025-02-14
 * @bodyParam loadDate string required Fecha de carga (Y-m-d). Example: 2025-02-15
 * @bodyParam salesperson integer ID del comercial. No-example
 * @bodyParam payment integer ID de condición de pago. No-example
 * @bodyParam incoterm integer ID de incoterm. No-example
 * @bodyParam buyerReference string Referencia del comprador. No-example
 * @bodyParam plannedProducts array Líneas planificadas (requerido si orderType no es autoventa). No-example
 * @bodyParam plannedProducts.*.product integer required ID del producto. Example: 1
 * @bodyParam plannedProducts.*.quantity number required Cantidad. Example: 100
 * @bodyParam plannedProducts.*.boxes integer required Número de cajas. Example: 10
 * @bodyParam plannedProducts.*.unitPrice number required Precio unitario. Example: 5.50
 * @bodyParam plannedProducts.*.tax integer required ID del impuesto. Example: 1
 * @bodyParam invoiceRequired boolean Requerido si orderType=autoventa. Con factura sí/no. No-example
 * @bodyParam observations string Observaciones (autoventa). No-example
 * @bodyParam items array Líneas por producto (autoventa). items.*.productId, boxesCount, totalWeight, unitPrice. No-example
 * @bodyParam boxes array Cajas a crear en el palet (autoventa). boxes.*.productId, lot, netWeight, gs1128. No-example
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
        $isAutoventa = $this->input('orderType') === Order::ORDER_TYPE_AUTOVENTA;

        $rules = [
            'orderType' => 'nullable|string|in:standard,autoventa',
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
            'plannedProducts' => $isAutoventa ? 'nullable|array' : 'nullable|array',
            'plannedProducts.*.product' => 'required_with:plannedProducts|integer|exists:tenant.products,id',
            'plannedProducts.*.quantity' => 'required_with:plannedProducts|numeric',
            'plannedProducts.*.boxes' => 'required_with:plannedProducts|integer',
            'plannedProducts.*.unitPrice' => 'required_with:plannedProducts|numeric',
            'plannedProducts.*.tax' => 'required_with:plannedProducts|integer|exists:tenant.taxes,id',
        ];

        if ($isAutoventa) {
            $rules['invoiceRequired'] = 'required|boolean';
            $rules['observations'] = 'nullable|string|max:1000';
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.productId'] = 'required|integer|exists:tenant.products,id';
            $rules['items.*.boxesCount'] = 'required|integer|min:1';
            $rules['items.*.totalWeight'] = 'required|numeric|min:0';
            $rules['items.*.unitPrice'] = 'required|numeric|min:0';
            $rules['items.*.subtotal'] = 'nullable|numeric|min:0';
            $rules['items.*.tax'] = 'nullable|integer|exists:tenant.taxes,id';
            $rules['boxes'] = 'required|array|min:1';
            $rules['boxes.*.productId'] = 'required|integer|exists:tenant.products,id';
            $rules['boxes.*.lot'] = 'nullable|string|max:255';
            $rules['boxes.*.netWeight'] = 'required|numeric|min:0.01';
            $rules['boxes.*.gs1128'] = 'nullable|string|max:255';
            $rules['boxes.*.grossWeight'] = 'nullable|numeric|min:0';
        }

        return $rules;
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
            'orderType.in' => 'El tipo de pedido debe ser standard o autoventa.',
            'invoiceRequired.required' => 'Debe indicar si la autoventa lleva factura.',
            'invoiceRequired.boolean' => 'El campo factura debe ser sí o no.',
            'items.required' => 'Debe incluir al menos una línea de producto en la autoventa.',
            'items.min' => 'Debe incluir al menos una línea de producto.',
            'items.*.productId.required' => 'El producto es obligatorio en cada línea.',
            'items.*.productId.exists' => 'Uno o más productos no existen.',
            'items.*.boxesCount.required' => 'El número de cajas es obligatorio en cada línea.',
            'items.*.boxesCount.min' => 'Debe haber al menos una caja por línea.',
            'items.*.totalWeight.required' => 'El peso total es obligatorio en cada línea.',
            'items.*.unitPrice.required' => 'El precio unitario es obligatorio en cada línea.',
            'boxes.required' => 'Debe incluir al menos una caja en la autoventa.',
            'boxes.min' => 'Debe incluir al menos una caja.',
            'boxes.*.productId.required' => 'El producto es obligatorio en cada caja.',
            'boxes.*.productId.exists' => 'Uno o más productos de las cajas no existen.',
            'boxes.*.netWeight.required' => 'El peso neto es obligatorio en cada caja.',
            'boxes.*.netWeight.min' => 'El peso neto debe ser mayor que 0.',
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
