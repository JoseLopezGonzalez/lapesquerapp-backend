<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalProcessor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalProcessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ExternalProcessor::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legalName' => ['nullable', 'string', 'max:255'],
            'vatNumber' => ['required', 'string', 'max:32', Rule::unique(ExternalProcessor::class, 'vat_number')],
            'sanitaryRegistrationNumber' => ['nullable', 'string', 'max:64'],
            'contactPerson' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['string', 'email:rfc,dns', 'distinct'],
            'ccEmails' => ['nullable', 'array'],
            'ccEmails.*' => ['string', 'email:rfc,dns', 'distinct'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'province' => ['nullable', 'string', 'max:255'],
            'countryId' => ['nullable', 'integer', 'exists:tenant.countries,id'],
            'isActive' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del transformador externo es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede superar 255 caracteres.',
            'vatNumber.required' => 'El CIF/NIF es obligatorio.',
            'vatNumber.string' => 'El CIF/NIF debe ser texto.',
            'vatNumber.max' => 'El CIF/NIF no puede superar 32 caracteres.',
            'vatNumber.unique' => 'Ya existe un transformador externo con ese CIF/NIF.',
            'sanitaryRegistrationNumber.max' => 'El número de registro sanitario no puede superar 64 caracteres.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
            'countryId.integer' => 'El país debe ser un número entero.',
            'countryId.exists' => 'El país seleccionado no existe.',
            'isActive.boolean' => 'El campo activo debe ser verdadero o falso.',
            'notes.max' => 'Las notas no pueden superar 2000 caracteres.',
        ];
    }
}
