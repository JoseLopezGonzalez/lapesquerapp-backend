<?php

namespace App\Http\Requests\v2;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('supplier');
        if ($id instanceof Supplier) {
            return $this->user()->can('update', $id);
        }
        if (! $id) {
            return false;
        }

        return $this->user()->can('update', Supplier::findOrFail($id));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $emailRule = app()->environment('testing') ? 'string|email:rfc|distinct' : 'string|email:rfc,dns|distinct';

        return [
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'emails' => 'nullable|array',
            'emails.*' => $emailRule,
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => $emailRule,
            'address' => 'nullable|string|max:1000',
            'cebo_export_type' => 'nullable|string|max:255',
            'a3erp_cebo_code' => 'nullable|string|max:255',
            'facilcom_cebo_code' => 'nullable|string|max:255',
            'facil_com_code' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del proveedor es obligatorio.',
            'name.max' => 'El nombre del proveedor no puede tener más de 255 caracteres.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
        ];
    }
}
