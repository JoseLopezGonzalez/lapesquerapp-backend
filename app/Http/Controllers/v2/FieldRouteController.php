<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\UpdateRouteStopRequest;
use App\Http\Resources\v2\DeliveryRouteResource;
use App\Models\DeliveryRoute;
use App\Models\FieldOperator;
use App\Models\RouteStop;
use Illuminate\Http\Request;

class FieldRouteController extends Controller
{
    private function getCurrentFieldOperatorId(Request $request): int
    {
        $user = $request->user();
        $fieldOperatorId = $user?->fieldOperator?->id
            ?? ($user ? FieldOperator::query()->where('user_id', $user->id)->value('id') : null);

        abort_unless($fieldOperatorId !== null, 403, 'No tienes una identidad operativa activa para acceder a este recurso.');

        return $fieldOperatorId;
    }

    public function index(Request $request)
    {
        $fieldOperatorId = $this->getCurrentFieldOperatorId($request);

        $query = DeliveryRoute::query()
            ->with(['stops', 'fieldOperator'])
            ->where('field_operator_id', $fieldOperatorId);

        return DeliveryRouteResource::collection($query->orderByDesc('route_date')->paginate($request->input('perPage', 10)));
    }

    public function show(DeliveryRoute $route)
    {
        $this->getCurrentFieldOperatorId(request());
        $this->authorize('viewAssigned', $route);

        return response()->json([
            'data' => new DeliveryRouteResource($route->load(['stops', 'fieldOperator'])),
        ]);
    }

    public function updateStop(UpdateRouteStopRequest $request, DeliveryRoute $route, RouteStop $routeStop)
    {
        $this->getCurrentFieldOperatorId($request);
        $this->authorize('updateAssignedStop', $route);

        abort_unless($routeStop->route_id === $route->id, 404);

        $validated = $request->validated();
        $routeStop->update([
            'status' => $validated['status'],
            'result_type' => $validated['result_type'] ?? null,
            'result_notes' => $validated['result_notes'] ?? null,
            'completed_at' => $validated['status'] === \App\Models\RouteStop::STATUS_COMPLETED ? now() : null,
        ]);

        return response()->json([
            'message' => 'Parada actualizada correctamente.',
            'data' => new DeliveryRouteResource($route->fresh(['stops', 'fieldOperator'])),
        ]);
    }
}
