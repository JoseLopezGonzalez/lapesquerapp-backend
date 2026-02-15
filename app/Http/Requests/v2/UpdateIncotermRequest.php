<?php

namespace App\Http\Requests\v2;

use App\Models\Incoterm;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIncotermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incoterm = Incoterm::findOrFail($this->route('incoterm'));

        return $this->user()->can('update', $incoterm);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('incoterm');

        return [
            'code' => 'required|string|max:10|unique:tenant.incoterms,code,' . $id,
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
