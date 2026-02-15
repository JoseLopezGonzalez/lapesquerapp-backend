<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class MoveMultipleToStorePalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'pallet_ids' => 'required|array|min:1',
            'pallet_ids.*' => 'integer|exists:tenant.pallets,id',
            'store_id' => 'required|integer|exists:tenant.stores,id',
        ];
    }
}
