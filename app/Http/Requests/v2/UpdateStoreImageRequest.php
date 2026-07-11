<?php

namespace App\Http\Requests\v2;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('store');
        if ($id instanceof Store) {
            return $this->user()->can('update', $id);
        }
        if (! $id) {
            return false;
        }

        return $this->user()->can('update', Store::findOrFail($id));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'La imagen es obligatoria.',
            'image.image' => 'El archivo debe ser una imagen.',
            'image.mimes' => 'La imagen debe ser jpg, jpeg, png o webp.',
            'image.max' => 'La imagen no puede superar 5 MB.',
        ];
    }
}
