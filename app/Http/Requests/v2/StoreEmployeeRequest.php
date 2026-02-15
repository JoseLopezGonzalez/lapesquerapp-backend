<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'nfc_uid' => 'required|string|unique:tenant.employees,nfc_uid',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'nfc_uid.required' => 'El UID NFC es obligatorio.',
            'nfc_uid.unique' => 'Ya existe un empleado con este UID NFC.',
        ];
    }
}
