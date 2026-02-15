<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StorePunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uid' => 'nullable|string|required_without:employee_id',
            'employee_id' => 'nullable|integer|exists:tenant.employees,id|required_without:uid',
            'device_id' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'uid.required_without' => 'Debe proporcionar uid o employee_id.',
            'employee_id.required_without' => 'Debe proporcionar uid o employee_id.',
            'employee_id.exists' => 'El empleado especificado no existe.',
        ];
    }
}
