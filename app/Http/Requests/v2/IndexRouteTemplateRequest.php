<?php

namespace App\Http\Requests\v2;

use App\Models\RouteTemplate;
use Illuminate\Foundation\Http\FormRequest;

class IndexRouteTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', RouteTemplate::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'fieldOperatorId' => 'nullable|integer|exists:tenant.field_operators,id',
            'salespersonId' => 'nullable|integer|exists:tenant.salespeople,id',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
