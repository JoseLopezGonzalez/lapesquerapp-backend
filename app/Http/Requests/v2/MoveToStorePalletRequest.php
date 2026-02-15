<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class MoveToStorePalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'pallet_id' => 'required|integer|exists:tenant.pallets,id',
            'store_id' => 'required|integer|exists:tenant.stores,id',
        ];
    }
}
