<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class DestroyMultipleEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.employees,id',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Debe proporcionar un array de IDs.',
            'ids.array' => 'Los IDs deben ser un array.',
            'ids.*.integer' => 'Cada ID debe ser un número entero.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
        ];
    }
}
