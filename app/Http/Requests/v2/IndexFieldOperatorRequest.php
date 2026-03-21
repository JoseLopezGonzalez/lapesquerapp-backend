<?php

namespace App\Http\Requests\v2;

use App\Models\FieldOperator;
use Illuminate\Foundation\Http\FormRequest;

class IndexFieldOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', FieldOperator::class);
    }

    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'name' => 'nullable|string|max:255',
            'userId' => 'nullable|integer|exists:tenant.users,id',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
