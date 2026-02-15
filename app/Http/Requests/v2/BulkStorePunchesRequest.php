<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

/** Validación para POST punches/bulk. */
class BulkStorePunchesRequest extends FormRequest
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
            'punches' => 'required|array|min:1',
            'punches.*.employee_id' => 'required|integer|exists:tenant.employees,id',
            'punches.*.event_type' => 'required|in:IN,OUT',
            'punches.*.timestamp' => 'required|date',
            'punches.*.device_id' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'punches.required' => 'Debe proporcionar un array de fichajes.',
            'punches.array' => 'Los fichajes deben ser un array.',
            'punches.min' => 'Debe proporcionar al menos un fichaje.',
            'punches.*.employee_id.required' => 'El ID del empleado es obligatorio.',
            'punches.*.employee_id.integer' => 'El ID del empleado debe ser un número entero.',
            'punches.*.employee_id.exists' => 'El empleado especificado no existe.',
            'punches.*.event_type.required' => 'El tipo de evento es obligatorio.',
            'punches.*.event_type.in' => 'El tipo de evento debe ser IN o OUT.',
            'punches.*.timestamp.required' => 'El timestamp es obligatorio.',
            'punches.*.timestamp.date' => 'El timestamp debe ser una fecha válida.',
        ];
    }
}
