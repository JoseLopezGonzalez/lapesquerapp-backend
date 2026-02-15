<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class LinkOrdersPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'pallets' => 'required|array|min:1',
            'pallets.*.id' => 'required|integer|exists:tenant.pallets,id',
            'pallets.*.orderId' => 'required|integer|exists:tenant.orders,id',
        ];
    }
}
