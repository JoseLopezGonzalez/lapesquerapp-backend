<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $paymentTerm = $this->route('payment_term');

        return $this->user()->can('update', $paymentTerm);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('payment_term')?->getKey();

        return [
            'name' => 'required|string|max:255|unique:tenant.payment_terms,name,' . $id,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un término de pago con este nombre.',
        ];
    }
}
