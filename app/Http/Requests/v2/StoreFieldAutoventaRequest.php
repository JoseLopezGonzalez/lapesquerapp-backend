<?php

namespace App\Http\Requests\v2;

use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\RouteStop;
use Illuminate\Foundation\Http\FormRequest;

class StoreFieldAutoventaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createAutoventaOperational', Order::class);
    }

    public function rules(): array
    {
        return [
            'customer' => 'nullable|integer|exists:tenant.customers,id',
            'newCustomerName' => 'nullable|string|max:255',
            'entryDate' => 'required|date',
            'loadDate' => 'required|date',
            'invoiceRequired' => 'required|boolean',
            'observations' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.productId' => 'required|integer|exists:tenant.products,id',
            'items.*.boxesCount' => 'required|integer|min:1',
            'items.*.totalWeight' => 'required|numeric|min:0',
            'items.*.unitPrice' => 'required|numeric|min:0',
            'items.*.subtotal' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|integer|exists:tenant.taxes,id',
            'boxes' => 'required|array|min:1',
            'boxes.*.productId' => 'required|integer|exists:tenant.products,id',
            'boxes.*.lot' => 'nullable|string|max:255',
            'boxes.*.netWeight' => 'required|numeric|min:0.01',
            'boxes.*.grossWeight' => 'nullable|numeric|min:0',
            'boxes.*.gs1128' => 'nullable|string|max:255',
            'routeId' => 'nullable|integer|exists:tenant.routes,id',
            'routeStopId' => 'nullable|integer|exists:tenant.route_stops,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('customer') && ! $this->filled('newCustomerName')) {
                $validator->errors()->add('customer', 'Debe seleccionar un cliente o indicar el nombre del cliente nuevo.');
            }

            if ($this->filled('entryDate') && $this->filled('loadDate') && $this->entryDate > $this->loadDate) {
                $validator->errors()->add('loadDate', 'La fecha de carga debe ser mayor o igual a la fecha de entrada.');
            }

            $routeId = $this->input('routeId');
            $routeStopId = $this->input('routeStopId');
            $fieldOperatorId = $this->user()?->fieldOperator?->id;

            $route = $routeId ? DeliveryRoute::find($routeId) : null;
            $routeStop = $routeStopId ? RouteStop::with('route')->find($routeStopId) : null;

            if ($route && $fieldOperatorId && $route->field_operator_id !== $fieldOperatorId) {
                $validator->errors()->add('routeId', 'La ruta seleccionada no está asignada al actor operativo actual.');
            }

            if ($routeStop && $fieldOperatorId && $routeStop->route?->field_operator_id !== $fieldOperatorId) {
                $validator->errors()->add('routeStopId', 'La parada seleccionada no pertenece a una ruta asignada al actor operativo actual.');
            }

            if ($route && $routeStop && $routeStop->route_id !== $route->id) {
                $validator->errors()->add('routeStopId', 'La parada seleccionada no pertenece a la ruta indicada.');
            }
        });
    }
}
