<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('employee') ?? $this->route('id');
        return [
            'name' => 'sometimes|required|string|max:255',
            'nfc_uid' => 'sometimes|required|string|unique:tenant.employees,nfc_uid,' . $id,
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'nfc_uid.required' => 'El UID NFC es obligatorio.',
            'nfc_uid.unique' => 'Ya existe otro empleado con este UID NFC.',
        ];
    }
}
