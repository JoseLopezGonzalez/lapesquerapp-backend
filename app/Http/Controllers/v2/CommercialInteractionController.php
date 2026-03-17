<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexCommercialInteractionRequest;
use App\Http\Requests\v2\StoreCommercialInteractionRequest;
use App\Http\Resources\v2\CommercialInteractionResource;
use App\Models\CommercialInteraction;
use App\Services\v2\CommercialInteractionService;

class CommercialInteractionController extends Controller
{
    public function index(IndexCommercialInteractionRequest $request)
    {
        return CommercialInteractionResource::collection(CommercialInteractionService::list($request));
    }

    public function store(StoreCommercialInteractionRequest $request)
    {
        $this->authorize('create', CommercialInteraction::class);
        $interaction = CommercialInteractionService::store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Interacción registrada correctamente.',
            'data' => new CommercialInteractionResource($interaction),
        ], 201);
    }

    public function show(string $id)
    {
        $interaction = CommercialInteraction::with(['salesperson', 'prospect.country', 'customer.country'])->findOrFail($id);
        $this->authorize('view', $interaction);

        return response()->json([
            'data' => new CommercialInteractionResource($interaction),
        ]);
    }
}
