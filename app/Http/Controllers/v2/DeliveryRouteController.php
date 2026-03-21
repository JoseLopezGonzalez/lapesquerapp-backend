<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexDeliveryRouteRequest;
use App\Http\Requests\v2\StoreDeliveryRouteRequest;
use App\Http\Requests\v2\UpdateDeliveryRouteRequest;
use App\Http\Resources\v2\DeliveryRouteResource;
use App\Models\DeliveryRoute;
use App\Services\v2\DeliveryRouteWriteService;

class DeliveryRouteController extends Controller
{
    public function index(IndexDeliveryRouteRequest $request)
    {
        $query = DeliveryRoute::query()->with(['salesperson', 'fieldOperator', 'stops']);

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->filled('fieldOperatorId')) {
            $query->where('field_operator_id', $request->integer('fieldOperatorId'));
        }

        if ($request->filled('salespersonId')) {
            $query->where('salesperson_id', $request->integer('salespersonId'));
        }

        if ($request->filled('routeDate')) {
            $query->whereDate('route_date', $request->input('routeDate'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return DeliveryRouteResource::collection($query->orderByDesc('route_date')->paginate($request->input('perPage', 10)));
    }

    public function store(StoreDeliveryRouteRequest $request)
    {
        $route = DeliveryRouteWriteService::store($request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Ruta creada correctamente.',
            'data' => new DeliveryRouteResource($route),
        ], 201);
    }

    public function show(DeliveryRoute $route)
    {
        $this->authorize('view', $route);

        return response()->json([
            'data' => new DeliveryRouteResource($route->load(['salesperson', 'fieldOperator', 'stops'])),
        ]);
    }

    public function update(UpdateDeliveryRouteRequest $request, DeliveryRoute $route)
    {
        $updated = DeliveryRouteWriteService::update($route, $request->validated());

        return response()->json([
            'message' => 'Ruta actualizada correctamente.',
            'data' => new DeliveryRouteResource($updated),
        ]);
    }

    public function destroy(DeliveryRoute $route)
    {
        $this->authorize('delete', $route);
        $route->delete();

        return response()->json(['message' => 'Ruta eliminada correctamente.']);
    }
}
