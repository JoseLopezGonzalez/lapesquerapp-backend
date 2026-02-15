<?php

namespace App\Http\Requests\v2;

use App\Models\Transport;
use Illuminate\Foundation\Http\FormRequest;

class IndexTransportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Transport::class);
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
            'name' => 'nullable|string',
            'address' => 'nullable|string',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
