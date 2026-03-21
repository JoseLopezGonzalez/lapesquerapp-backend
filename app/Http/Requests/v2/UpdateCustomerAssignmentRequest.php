<?php

namespace App\Http\Requests\v2;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');
        if (! $customer instanceof Customer) {
            $customer = Customer::find($customer);
        }

        return ! $customer || $this->user()->can('update', $customer);
    }

    public function rules(): array
    {
        return [
            'salesperson_id' => 'sometimes|nullable|exists:tenant.salespeople,id',
            'field_operator_id' => 'sometimes|nullable|exists:tenant.field_operators,id',
            'operational_status' => 'sometimes|string|in:normal,alta_operativa',
        ];
    }
}
