<?php

namespace App\Http\Requests\v2;

use App\Models\Transport;
use Illuminate\Foundation\Http\FormRequest;

class DestroyMultipleTransportsRequest extends FormRequest
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
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.transports,id',
        ];
    }
}
