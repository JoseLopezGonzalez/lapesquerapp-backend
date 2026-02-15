<?php

namespace App\Http\Requests\v2;

use App\Models\FishingGear;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFishingGearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fishingGear = FishingGear::findOrFail($this->route('fishing_gear'));

        return $this->user()->can('update', $fishingGear);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('fishing_gear');

        return [
            'name' => 'required|string|min:2|unique:tenant.fishing_gears,name,' . $id,
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
