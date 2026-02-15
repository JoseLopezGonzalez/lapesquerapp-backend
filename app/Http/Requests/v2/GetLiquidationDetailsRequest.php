<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class GetLiquidationDetailsRequest extends FormRequest
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
            'dates.start' => 'required|date',
            'dates.end' => 'required|date|after_or_equal:dates.start',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dates.start.required' => 'La fecha de inicio es obligatoria.',
            'dates.start.date' => 'La fecha de inicio no es válida.',
            'dates.end.required' => 'La fecha de fin es obligatoria.',
            'dates.end.date' => 'La fecha de fin no es válida.',
            'dates.end.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
        ];
    }
}
