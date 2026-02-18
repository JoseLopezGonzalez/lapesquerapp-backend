<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleTransportsRequest;
use App\Http\Requests\v2\IndexTransportRequest;
use App\Http\Requests\v2\StoreTransportRequest;
use App\Http\Requests\v2\UpdateTransportRequest;
use App\Http\Resources\v2\TransportResource;
use App\Models\Transport;
use App\Services\TransportListService;
use Illuminate\Http\JsonResponse;

class TransportController extends Controller
{
    public function __construct(
        private TransportListService $listService
    ) {}

    public function index(IndexTransportRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['perPage'] ?? 12);
        $paginator = $this->listService->list($filters, $perPage);

        return TransportResource::collection($paginator)->response();
    }

    public function store(StoreTransportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $allEmails = [];
        foreach ($validated['emails'] as $email) {
            $allEmails[] = trim($email);
        }
        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:' . trim($email);
        }
        $emailsText = implode(";\n", $allEmails) . ';';

        $transport = Transport::create([
            'name' => $validated['name'],
            'vat_number' => $validated['vatNumber'],
            'address' => $validated['address'],
            'emails' => $emailsText,
        ]);

        return response()->json([
            'message' => 'Transportista creado correctamente.',
            'data' => new TransportResource($transport),
        ], 201);
    }

    public function show(Transport $transport): JsonResponse
    {
        $this->authorize('view', $transport);

        return response()->json([
            'message' => 'Transportista obtenido con éxito',
            'data' => new TransportResource($transport),
        ]);
    }

    public function update(UpdateTransportRequest $request, Transport $transport): JsonResponse
    {
        $validated = $request->validated();
        $allEmails = [];
        foreach ($validated['emails'] as $email) {
            $allEmails[] = trim($email);
        }
        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:' . trim($email);
        }
        $emailsText = implode(";\n", $allEmails) . ';';

        $transport->update([
            'name' => $validated['name'],
            'vat_number' => $validated['vatNumber'],
            'address' => $validated['address'],
            'emails' => $emailsText,
        ]);

        return response()->json([
            'message' => 'Transportista actualizado con éxito',
            'data' => new TransportResource($transport),
        ]);
    }

    public function destroy(Transport $transport): JsonResponse
    {
        $this->authorize('delete', $transport);

        $usedInOrders = $transport->orders()->exists();
        $usedInCustomers = $transport->customers()->exists();

        if ($usedInOrders || $usedInCustomers) {
            $reasons = [];
            if ($usedInOrders) {
                $reasons[] = 'pedidos';
            }
            if ($usedInCustomers) {
                $reasons[] = 'clientes';
            }
            $reasonsText = implode(' y ', $reasons);

            return response()->json([
                'message' => 'No se puede eliminar el transporte porque está en uso',
                'details' => 'El transporte está siendo utilizado en: ' . $reasonsText,
                'userMessage' => 'No se puede eliminar el transporte porque está siendo utilizado en: ' . $reasonsText,
            ], 400);
        }

        $transport->delete();

        return response()->json(['message' => 'Transporte eliminado con éxito.']);
    }

    public function destroyMultiple(DestroyMultipleTransportsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $transports = Transport::whereIn('id', $validated['ids'])->get();

        $inUse = [];
        foreach ($transports as $transport) {
            $usedInOrders = $transport->orders()->exists();
            $usedInCustomers = $transport->customers()->exists();

            if ($usedInOrders || $usedInCustomers) {
                $reasons = [];
                if ($usedInOrders) {
                    $reasons[] = 'pedidos';
                }
                if ($usedInCustomers) {
                    $reasons[] = 'clientes';
                }
                $inUse[] = [
                    'id' => $transport->id,
                    'name' => $transport->name,
                    'reasons' => implode(' y ', $reasons),
                ];
            }
        }

        if (! empty($inUse)) {
            $details = array_map(fn ($item) => $item['name'] . ' (usado en: ' . $item['reasons'] . ')', $inUse);

            return response()->json([
                'message' => 'No se pueden eliminar algunos transportes porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => 'No se pueden eliminar algunos transportes porque están en uso: ' . implode(', ', array_column($inUse, 'name')),
            ], 400);
        }

        Transport::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Transportes eliminados con éxito.']);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', Transport::class);

        $transports = Transport::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($transports);
    }
}
