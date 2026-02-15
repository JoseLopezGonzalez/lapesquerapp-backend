<?php

namespace App\Http\Requests\v2;

use App\Models\Incoterm;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncotermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Incoterm::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:10|unique:tenant.incoterms,code',
            'description' => 'required|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ya existe un incoterm con este código.',
        ];
    }
}
