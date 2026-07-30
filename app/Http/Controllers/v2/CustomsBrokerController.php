<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreCustomsBrokerRequest;
use App\Http\Requests\v2\UpdateCustomsBrokerRequest;
use App\Http\Resources\v2\CustomsBrokerResource;
use App\Models\CustomsBroker;
use Illuminate\Http\JsonResponse;

class CustomsBrokerController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', CustomsBroker::class);

        $customsBrokers = CustomsBroker::orderBy('name', 'asc')->get();

        return response()->json([
            'data' => CustomsBrokerResource::collection($customsBrokers),
        ]);
    }

    public function store(StoreCustomsBrokerRequest $request): JsonResponse
    {
        $customsBroker = CustomsBroker::create($request->validated());

        return response()->json([
            'message' => 'Agente de aduanas creado correctamente.',
            'data' => new CustomsBrokerResource($customsBroker),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $customsBroker = CustomsBroker::findOrFail($id);
        $this->authorize('view', $customsBroker);

        return response()->json([
            'data' => new CustomsBrokerResource($customsBroker),
        ]);
    }

    public function update(UpdateCustomsBrokerRequest $request, string $id): JsonResponse
    {
        $customsBroker = CustomsBroker::findOrFail($id);

        $customsBroker->update($request->validated());

        return response()->json([
            'message' => 'Agente de aduanas actualizado correctamente.',
            'data' => new CustomsBrokerResource($customsBroker),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $customsBroker = CustomsBroker::findOrFail($id);
        $this->authorize('delete', $customsBroker);

        if ($customsBroker->shippingDetails()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el agente de aduanas porque está en uso',
                'userMessage' => 'No se puede eliminar el agente de aduanas porque está siendo utilizado en uno o más pedidos.',
            ], 400);
        }

        $customsBroker->delete();

        return response()->json(['message' => 'Agente de aduanas eliminado correctamente.']);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', CustomsBroker::class);

        $customsBrokers = CustomsBroker::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($customsBrokers);
    }
}
