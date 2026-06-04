<?php

namespace App\Http\Requests\v2;

use App\Enums\Role;
use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class PalletExpeditionLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && ! $this->user()->hasRole(Role::Comercial->value)
            && $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'palletIds' => ['required', 'array', 'min:1'],
            'palletIds.*' => ['required', 'integer', 'distinct', 'exists:tenant.pallets,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'palletIds.required' => 'Debe proporcionar al menos un palet.',
            'palletIds.min' => 'Debe proporcionar al menos un palet.',
            'palletIds.*.exists' => 'Uno de los palets solicitados no existe.',
            'palletIds.*.distinct' => 'No puede solicitar el mismo palet varias veces.',
        ];
    }
}
