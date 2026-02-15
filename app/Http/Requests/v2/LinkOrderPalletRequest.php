<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class LinkOrderPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');
        return $id && $this->user()->can('update', Pallet::findOrFail($id));
    }

    public function rules(): array
    {
        return [
            'orderId' => 'required|integer|exists:tenant.orders,id',
        ];
    }
}
