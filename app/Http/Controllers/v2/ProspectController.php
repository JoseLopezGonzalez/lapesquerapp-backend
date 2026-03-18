<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexProspectRequest;
use App\Http\Requests\v2\ScheduleProspectActionRequest;
use App\Http\Requests\v2\StoreProspectContactRequest;
use App\Http\Requests\v2\StoreProspectRequest;
use App\Http\Requests\v2\UpdateProspectContactRequest;
use App\Http\Requests\v2\UpdateProspectRequest;
use App\Http\Resources\v2\ProspectResource;
use App\Models\Prospect;
use App\Models\ProspectContact;
use App\Services\v2\ProspectService;

class ProspectController extends Controller
{
    public function index(IndexProspectRequest $request)
    {
        return ProspectResource::collection(ProspectService::list($request));
    }

    public function store(StoreProspectRequest $request)
    {
        $result = ProspectService::store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Prospecto creado correctamente.',
            'data' => new ProspectResource($result['prospect']),
            'warnings' => $result['warnings'],
        ], 201);
    }

    public function show(string $id)
    {
        $prospect = Prospect::with(['country', 'salesperson', 'customer', 'contacts', 'primaryContact', 'latestInteraction.salesperson', 'interactions.salesperson', 'offers.lines.product', 'offers.lines.tax'])
            ->findOrFail($id);
        $this->authorize('view', $prospect);

        return response()->json([
            'data' => new ProspectResource($prospect),
        ]);
    }

    public function update(UpdateProspectRequest $request, string $id)
    {
        $prospect = Prospect::with(['contacts'])->findOrFail($id);
        $this->authorize('update', $prospect);

        $result = ProspectService::update($prospect, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Prospecto actualizado correctamente.',
            'data' => new ProspectResource($result['prospect']),
            'warnings' => $result['warnings'],
        ]);
    }

    public function destroy(string $id)
    {
        $prospect = Prospect::findOrFail($id);
        $this->authorize('delete', $prospect);
        ProspectService::delete($prospect);

        return response()->json(['message' => 'Prospecto eliminado correctamente.']);
    }

    public function contacts(string $id)
    {
        $prospect = Prospect::with('contacts')->findOrFail($id);
        $this->authorize('view', $prospect);

        return response()->json([
            'data' => $prospect->contacts->map->toArrayAssoc()->values(),
        ]);
    }

    public function storeContact(StoreProspectContactRequest $request, string $id)
    {
        $prospect = Prospect::findOrFail($id);
        $this->authorize('update', $prospect);
        $contact = ProspectService::storeContact($prospect, $request->validated());

        return response()->json([
            'message' => 'Contacto creado correctamente.',
            'data' => $contact->toArrayAssoc(),
        ], 201);
    }

    public function updateContact(UpdateProspectContactRequest $request, string $id, string $contactId)
    {
        $prospect = Prospect::findOrFail($id);
        $this->authorize('update', $prospect);
        $contact = ProspectContact::where('prospect_id', $prospect->id)->findOrFail($contactId);
        $contact = ProspectService::updateContact($prospect, $contact, $request->validated());

        return response()->json([
            'message' => 'Contacto actualizado correctamente.',
            'data' => $contact->toArrayAssoc(),
        ]);
    }

    public function destroyContact(string $id, string $contactId)
    {
        $prospect = Prospect::findOrFail($id);
        $this->authorize('update', $prospect);
        $contact = ProspectContact::where('prospect_id', $prospect->id)->findOrFail($contactId);
        ProspectService::deleteContact($contact);

        return response()->json(['message' => 'Contacto eliminado correctamente.']);
    }

    public function convertToCustomer(string $id)
    {
        $prospect = Prospect::with(['primaryContact', 'offers'])->findOrFail($id);
        $this->authorize('update', $prospect);
        $customer = ProspectService::convertToCustomer($prospect);

        return response()->json([
            'message' => 'Prospecto convertido a cliente correctamente.',
            'data' => $customer->toArrayAssoc(),
        ]);
    }

    public function scheduleAction(ScheduleProspectActionRequest $request, string $id)
    {
        $prospect = Prospect::findOrFail($id);
        $this->authorize('update', $prospect);
        $prospect = ProspectService::scheduleAction(
            $prospect,
            $request->validated()['nextActionAt'],
            $request->validated()['nextActionNote'] ?? null
        );

        return response()->json([
            'message' => 'Acción reprogramada correctamente.',
            'data' => new ProspectResource($prospect),
        ]);
    }

    public function clearNextAction(string $id)
    {
        $prospect = Prospect::findOrFail($id);
        $this->authorize('update', $prospect);
        $prospect = ProspectService::clearNextAction($prospect);

        return response()->json([
            'message' => 'Próxima acción eliminada correctamente.',
            'data' => new ProspectResource($prospect),
        ]);
    }
}
