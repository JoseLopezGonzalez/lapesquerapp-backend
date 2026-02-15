<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

/** Validación para fichaje manual (employee_id + event_type + timestamp). */
class StoreManualPunchRequest extends FormRequest
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
        return self::getRules();
    }

    /** Reglas estáticas para reutilizar cuando la entrada viene de store() (Request en lugar de Form Request). */
    public static function getRules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:tenant.employees,id',
            'event_type' => 'required|in:IN,OUT',
            'timestamp' => 'required|date',
            'device_id' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return self::getMessages();
    }

    public static function getMessages(): array
    {
        return [
            'employee_id.required' => 'El ID del empleado es obligatorio.',
            'employee_id.integer' => 'El ID del empleado debe ser un número entero.',
            'employee_id.exists' => 'El empleado especificado no existe.',
            'event_type.required' => 'El tipo de evento es obligatorio.',
            'event_type.in' => 'El tipo de evento debe ser IN o OUT.',
            'timestamp.required' => 'El timestamp es obligatorio.',
            'timestamp.date' => 'El timestamp debe ser una fecha válida en formato ISO 8601.',
        ];
    }
}

