<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class SalesBySalespersonRequest extends FormRequest
{
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
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dateFrom.required' => 'La fecha de inicio es obligatoria.',
            'dateFrom.date' => 'La fecha de inicio no tiene un formato válido.',
            'dateTo.required' => 'La fecha de fin es obligatoria.',
            'dateTo.date' => 'La fecha de fin no tiene un formato válido.',
        ];
    }
}
