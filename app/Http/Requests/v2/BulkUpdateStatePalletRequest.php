<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatePalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'status' => 'required|integer|in:1,2,3,4',
            'ids' => 'array|required_without_all:filters,applyToAll',
            'ids.*' => 'integer|exists:tenant.pallets,id',
            'filters' => 'array|required_without_all:ids,applyToAll',
            'applyToAll' => 'boolean|required_without_all:ids,filters',
        ];
    }
}
