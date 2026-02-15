<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class SearchByLotPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'lot' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'lot.required' => 'El parámetro lot es requerido.',
        ];
    }
}
