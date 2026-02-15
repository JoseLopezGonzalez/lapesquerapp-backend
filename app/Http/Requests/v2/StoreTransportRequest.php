<?php

namespace App\Http\Requests\v2;

use App\Models\Transport;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Transport::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|unique:tenant.transports,name',
            'vatNumber' => 'required|string|regex:/^[A-Z0-9]{8,12}$/|unique:tenant.transports,vat_number',
            'address' => 'required|string|min:10',
            'emails' => 'required|array|min:1',
            'emails.*' => 'email',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'email',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un transporte con este nombre.',
            'vatNumber.unique' => 'Ya existe un transporte con este NIF/CIF.',
            'vatNumber.regex' => 'El NIF/CIF debe tener entre 8 y 12 caracteres alfanuméricos en mayúsculas.',
            'emails.required' => 'Debe proporcionar al menos un email.',
            'emails.min' => 'Debe proporcionar al menos un email.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
        ];
    }
}
