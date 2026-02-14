<?php

namespace App\Http\Requests\v2;

use App\Models\CeboDispatch;
use Illuminate\Foundation\Http\FormRequest;

class IndexCeboDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CeboDispatch::class);
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
            'suppliers' => 'nullable|array',
            'suppliers.*' => 'integer',
            'dates' => 'nullable|array',
            'dates.start' => 'nullable|date',
            'dates.end' => 'nullable|date',
            'species' => 'nullable|array',
            'species.*' => 'integer',
            'products' => 'nullable|array',
            'products.*' => 'integer',
            'notes' => 'nullable|string|max:1000',
            'export_type' => 'nullable|string|max:255',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
