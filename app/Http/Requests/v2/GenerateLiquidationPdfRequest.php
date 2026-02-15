<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class GenerateLiquidationPdfRequest extends FormRequest
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
            'receptions' => 'nullable|array',
            'receptions.*' => 'integer|exists:tenant.raw_material_receptions,id',
            'dispatches' => 'nullable|array',
            'dispatches.*' => 'integer|exists:tenant.cebo_dispatches,id',
            'payment_method' => 'nullable|in:cash,transfer',
            'has_management_fee' => 'nullable|in:0,1,true,false',
            'show_transfer_payment' => 'nullable|in:0,1,true,false',
        ];
    }
}
