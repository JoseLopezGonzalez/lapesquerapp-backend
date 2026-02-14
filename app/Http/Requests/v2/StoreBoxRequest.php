<?php

namespace App\Http\Requests\v2;

use App\Models\Box;
use Illuminate\Foundation\Http\FormRequest;

class StoreBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Box::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'article_id' => 'required|exists:tenant.products,id',
            'lot' => 'required|string|max:255',
            'gs1_128' => 'nullable|string|max:255',
            'gross_weight' => 'nullable|numeric|min:0',
            'net_weight' => 'required|numeric|min:0.01',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'article_id.required' => 'El producto es obligatorio.',
            'article_id.exists' => 'El producto seleccionado no existe.',
            'lot.required' => 'El lote es obligatorio.',
            'net_weight.required' => 'El peso neto es obligatorio.',
            'net_weight.numeric' => 'El peso neto debe ser un número.',
            'net_weight.min' => 'El peso neto debe ser mayor que 0.',
        ];
    }
}
