<?php

namespace App\Http\Requests\v2;

use App\Models\RouteStop;
use App\Models\RouteTemplate;
use Illuminate\Foundation\Http\FormRequest;

class StoreRouteTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RouteTemplate::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'salespersonId' => 'nullable|integer|exists:tenant.salespeople,id',
            'fieldOperatorId' => 'nullable|integer|exists:tenant.field_operators,id',
            'isActive' => 'nullable|boolean',
            'stops' => 'nullable|array',
            'stops.*.position' => 'required_with:stops|integer|min:1',
            'stops.*.stopType' => 'required_with:stops|string|in:' . implode(',', RouteStop::validStopTypes()),
            'stops.*.targetType' => 'nullable|string|in:customer,prospect,location',
            'stops.*.customerId' => 'nullable|integer|exists:tenant.customers,id',
            'stops.*.prospectId' => 'nullable|integer|exists:tenant.prospects,id',
            'stops.*.label' => 'nullable|string|max:255',
            'stops.*.address' => 'nullable|string|max:1000',
            'stops.*.notes' => 'nullable|string|max:1000',
        ];
    }
}
