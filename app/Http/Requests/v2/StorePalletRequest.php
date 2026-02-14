<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class StorePalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'observations' => 'nullable|string|max:1000',
            'boxes' => 'required|array|min:1',
            'boxes.*.product.id' => 'required|exists:tenant.products,id',
            'boxes.*.lot' => 'required|string|max:255',
            'boxes.*.gs1128' => 'required|string|max:255',
            'boxes.*.grossWeight' => 'required|numeric|min:0',
            'boxes.*.netWeight' => 'required|numeric|min:0.01',
            'store.id' => 'sometimes|nullable|integer|exists:tenant.stores,id',
            'orderId' => 'sometimes|nullable|integer|exists:tenant.orders,id',
            'state.id' => 'sometimes|integer|in:1,2,3,4',
        ];
    }

    public function messages(): array
    {
        return [
            'boxes.required' => 'Debe incluir al menos una caja.',
            'boxes.*.product.id.exists' => 'Uno de los productos no existe.',
            'boxes.*.netWeight.min' => 'El peso neto de cada caja debe ser mayor que 0.',
        ];
    }
}
