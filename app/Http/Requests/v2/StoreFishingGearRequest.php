<?php

namespace App\Http\Requests\v2;

use App\Models\FishingGear;
use Illuminate\Foundation\Http\FormRequest;

class StoreFishingGearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FishingGear::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|unique:tenant.fishing_gears,name',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un arte de pesca con este nombre.',
        ];
    }
}
