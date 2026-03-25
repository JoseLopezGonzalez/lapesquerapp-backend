<?php

namespace App\Http\Requests\v2;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');
        if (! $order instanceof Order) {
            $order = Order::find($order);
        }

        return ! $order || $this->user()->can('updateOperational', $order);
    }

    public function rules(): array
    {
        return [
            // Legacy fields (previous contract) are explicitly forbidden.
            'status' => 'prohibited',
            'plannedProducts' => 'prohibited',

            // Execution payload (same shape as autoventa where it applies)
            'items' => 'sometimes|array',
            'items.*.productId' => 'required|integer|exists:tenant.products,id',
            'items.*.boxesCount' => 'required|integer|min:1',
            'items.*.totalWeight' => 'required|numeric|min:0',
            'items.*.unitPrice' => 'required|numeric|min:0',
            'items.*.subtotal' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|integer|exists:tenant.taxes,id',

            'boxes' => 'sometimes|array|min:1',
            'boxes.*.productId' => 'required|integer|exists:tenant.products,id',
            'boxes.*.lot' => 'nullable|string|max:255',
            'boxes.*.netWeight' => 'required|numeric|min:0.01',
            'boxes.*.grossWeight' => 'nullable|numeric|min:0',
            'boxes.*.gs1128' => 'nullable|string|max:255',

            // Planned additions for non-prefijado products (only new lines)
            'plannedExtras' => 'sometimes|array|min:1',
            'plannedExtras.*.productId' => 'required|integer|exists:tenant.products,id',
            'plannedExtras.*.unitPrice' => 'required|numeric|min:0',
            'plannedExtras.*.taxId' => 'required|integer|exists:tenant.taxes,id',

            // Planned adjustments on existing prefijado lines (only price & tax)
            'plannedAdjustments' => 'sometimes|array|min:1',
            'plannedAdjustments.*.plannedProductDetailId' => 'required|integer|exists:tenant.order_planned_product_details,id',
            'plannedAdjustments.*.unitPrice' => 'required|numeric|min:0',
            'plannedAdjustments.*.taxId' => 'required|integer|exists:tenant.taxes,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->has('boxes') && ! $this->has('plannedExtras') && ! $this->has('plannedAdjustments')) {
                $validator->errors()->add('boxes', 'Debe indicar cajas escaneadas, productos extra o ajustes de líneas planificadas.');
            }
        });
    }
}
