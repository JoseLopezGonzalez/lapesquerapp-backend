<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class DestroyMultiplePalletsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.pallets,id',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Debe proporcionar al menos un ID para eliminar.',
            'ids.min' => 'Debe proporcionar al menos un ID válido para eliminar.',
        ];
    }
}
