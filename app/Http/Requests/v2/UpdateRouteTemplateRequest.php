<?php

namespace App\Http\Requests\v2;

use App\Models\RouteStop;
use App\Models\RouteTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('route_template') ?? $this->route('routeTemplate');
        if (! $template instanceof RouteTemplate) {
            $template = RouteTemplate::find($template);
        }

        return ! $template || $this->user()->can('update', $template);
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:5000',
            'salespersonId' => 'sometimes|nullable|integer|exists:tenant.salespeople,id',
            'fieldOperatorId' => 'sometimes|nullable|integer|exists:tenant.field_operators,id',
            'isActive' => 'sometimes|boolean',
            'stops' => 'sometimes|array',
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
