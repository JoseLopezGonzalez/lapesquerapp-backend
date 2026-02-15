<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class AvailableForOrderPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'orderId' => 'required|integer|exists:tenant.orders,id',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'storeId' => 'nullable|integer|exists:tenant.stores,id',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->all(), $this->route()->parameters());
    }
}
