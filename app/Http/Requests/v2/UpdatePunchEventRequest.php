<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

/** Validación para PUT/PATCH punches/{id}. */
class UpdatePunchEventRequest extends FormRequest
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
            'employee_id' => 'sometimes|required|integer|exists:tenant.employees,id',
            'event_type' => 'sometimes|required|in:IN,OUT',
            'device_id' => 'sometimes|required|string',
            'timestamp' => 'sometimes|required|date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.exists' => 'El empleado especificado no existe.',
            'event_type.in' => 'El tipo de evento debe ser IN o OUT.',
            'timestamp.date' => 'El timestamp debe ser una fecha válida.',
        ];
    }
}
