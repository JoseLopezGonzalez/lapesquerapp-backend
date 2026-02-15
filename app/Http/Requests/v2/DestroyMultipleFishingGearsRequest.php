<?php

namespace App\Http\Requests\v2;

use App\Models\FishingGear;
use Illuminate\Foundation\Http\FormRequest;

class DestroyMultipleFishingGearsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', FishingGear::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.fishing_gears,id',
        ];
    }
}
