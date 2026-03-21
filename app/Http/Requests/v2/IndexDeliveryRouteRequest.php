<?php

namespace App\Http\Requests\v2;

use App\Models\DeliveryRoute;
use Illuminate\Foundation\Http\FormRequest;

class IndexDeliveryRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', DeliveryRoute::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'fieldOperatorId' => 'nullable|integer|exists:tenant.field_operators,id',
            'salespersonId' => 'nullable|integer|exists:tenant.salespeople,id',
            'routeDate' => 'nullable|date',
            'status' => 'nullable|string|in:' . implode(',', DeliveryRoute::validStatuses()),
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
