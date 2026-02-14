<?php

namespace App\Http\Requests\v2;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;

class IndexStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Store::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'name' => 'nullable|string|max:255',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
