<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePalletMaritimeContainerRequest extends FormRequest
{
    /**
     * La autorización se resuelve en el controlador contra el pedido (Order::update).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'containerId' => 'nullable|integer',
        ];
    }
}
