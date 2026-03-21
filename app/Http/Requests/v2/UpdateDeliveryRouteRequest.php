<?php

namespace App\Http\Requests\v2;

use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $route = $this->route('delivery_route') ?? $this->route('route');
        if (! $route instanceof DeliveryRoute) {
            $route = DeliveryRoute::find($route);
        }

        return ! $route || $this->user()->can('update', $route);
    }

    public function rules(): array
    {
        return [
            'routeTemplateId' => 'sometimes|nullable|integer|exists:tenant.route_templates,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:5000',
            'routeDate' => 'sometimes|nullable|date',
            'status' => 'sometimes|string|in:' . implode(',', DeliveryRoute::validStatuses()),
            'salespersonId' => 'sometimes|nullable|integer|exists:tenant.salespeople,id',
            'fieldOperatorId' => 'sometimes|nullable|integer|exists:tenant.field_operators,id',
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
