<?php

namespace App\Http\Requests\v2;

use App\Models\Country;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $country = Country::findOrFail($this->route('country'));

        return $this->user()->can('update', $country);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('country');

        return [
            'name' => 'required|string|min:2|max:255|unique:tenant.countries,name,' . $id,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un país con este nombre.',
        ];
    }
}
