<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class AssignToPositionPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'position_id' => 'required|integer|min:1',
            'pallet_ids' => 'required|array|min:1',
            'pallet_ids.*' => 'integer|exists:tenant.pallets,id',
        ];
    }
}
