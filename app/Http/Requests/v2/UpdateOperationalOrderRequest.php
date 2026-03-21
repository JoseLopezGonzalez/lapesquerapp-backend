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
            'status' => 'sometimes|string|in:pending,finished,incident',
            'plannedProducts' => 'sometimes|array|min:1',
            'plannedProducts.*.product' => 'required|integer|exists:tenant.products,id',
            'plannedProducts.*.quantity' => 'required|numeric|min:0',
            'plannedProducts.*.boxes' => 'required|integer|min:0',
            'plannedProducts.*.unitPrice' => 'required|numeric|min:0',
            'plannedProducts.*.tax' => 'required|integer|exists:tenant.taxes,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->has('status') && ! $this->has('plannedProducts')) {
                $validator->errors()->add('plannedProducts', 'Debe indicar un nuevo estado, nuevas líneas operativas o ambos.');
            }
        });
    }
}
