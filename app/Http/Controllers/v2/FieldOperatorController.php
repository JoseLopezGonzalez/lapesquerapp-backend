<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexFieldOperatorRequest;
use App\Http\Requests\v2\StoreFieldOperatorRequest;
use App\Http\Requests\v2\UpdateFieldOperatorRequest;
use App\Http\Resources\v2\FieldOperatorResource;
use App\Models\FieldOperator;
use App\Services\v2\FieldOperatorListService;

class FieldOperatorController extends Controller
{
    public function index(IndexFieldOperatorRequest $request)
    {
        return FieldOperatorResource::collection(FieldOperatorListService::list($request));
    }

    public function store(StoreFieldOperatorRequest $request)
    {
        $validated = $request->validated();
        $fieldOperator = FieldOperator::create([
            'name' => $validated['name'],
            'emails' => $this->formatEmails($validated['emails'] ?? [], $validated['ccEmails'] ?? []),
            'user_id' => $validated['user_id'] ?? null,
        ]);

        $fieldOperator->load('user');

        return response()->json([
            'message' => 'Actor operativo creado correctamente.',
            'data' => new FieldOperatorResource($fieldOperator),
        ], 201);
    }

    public function show(FieldOperator $fieldOperator)
    {
        $this->authorize('view', $fieldOperator);
        $fieldOperator->load('user');

        return response()->json([
            'data' => new FieldOperatorResource($fieldOperator),
        ]);
    }

    public function update(UpdateFieldOperatorRequest $request, FieldOperator $fieldOperator)
    {
        $validated = $request->validated();
        $fieldOperator->update([
            'name' => $validated['name'] ?? $fieldOperator->name,
            'emails' => array_key_exists('emails', $validated) || array_key_exists('ccEmails', $validated)
                ? $this->formatEmails($validated['emails'] ?? [], $validated['ccEmails'] ?? [])
                : $fieldOperator->emails,
            'user_id' => array_key_exists('user_id', $validated) ? $validated['user_id'] : $fieldOperator->user_id,
        ]);

        $fieldOperator->load('user');

        return response()->json([
            'message' => 'Actor operativo actualizado correctamente.',
            'data' => new FieldOperatorResource($fieldOperator),
        ]);
    }

    public function destroy(FieldOperator $fieldOperator)
    {
        $this->authorize('delete', $fieldOperator);

        if ($fieldOperator->customers()->exists() || $fieldOperator->orders()->exists() || $fieldOperator->routes()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el actor operativo porque está en uso.',
                'userMessage' => 'No se puede eliminar el actor operativo porque tiene clientes, pedidos o rutas asociadas.',
            ], 400);
        }

        $fieldOperator->delete();

        return response()->json(['message' => 'Actor operativo eliminado correctamente.']);
    }

    public function options()
    {
        $this->authorize('viewAny', FieldOperator::class);

        return response()->json(
            FieldOperator::select('id', 'name')->orderBy('name')->get()
        );
    }

    private function formatEmails(array $emails, array $ccEmails): ?string
    {
        $all = [];
        foreach ($emails as $email) {
            $all[] = trim($email);
        }
        foreach ($ccEmails as $email) {
            $all[] = 'CC:' . trim($email);
        }

        return count($all) > 0 ? implode(";\n", $all) . ';' : null;
    }
}
