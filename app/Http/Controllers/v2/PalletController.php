<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\AssignToPositionPalletRequest;
use App\Http\Requests\v2\AvailableForOrderPalletRequest;
use App\Http\Requests\v2\BulkUpdateStatePalletRequest;
use App\Http\Requests\v2\DestroyMultiplePalletsRequest;
use App\Http\Requests\v2\IndexPalletRequest;
use App\Http\Requests\v2\LinkOrderPalletRequest;
use App\Http\Requests\v2\LinkOrdersPalletRequest;
use App\Http\Requests\v2\MoveMultipleToStorePalletRequest;
use App\Http\Requests\v2\MoveToStorePalletRequest;
use App\Http\Requests\v2\SearchByLotPalletRequest;
use App\Http\Requests\v2\StorePalletRequest;
use App\Http\Requests\v2\UnlinkOrdersPalletRequest;
use App\Http\Requests\v2\UpdatePalletRequest;
use App\Http\Resources\v2\PalletResource;
use App\Services\v2\PalletActionService;
use App\Services\v2\PalletListService;
use App\Services\v2\PalletWriteService;
use App\Models\Pallet;

class PalletController extends Controller
{
    public function index(IndexPalletRequest $request)
    {
        return PalletResource::collection(PalletListService::list($request));
    }

    public function store(StorePalletRequest $request)
    {
        $newPallet = PalletWriteService::store($request->validated());

        return response()->json(new PalletResource($newPallet), 201);
    }

    public function show(string $id)
    {
        $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $id))->firstOrFail();
        $this->authorize('view', $pallet);

        return response()->json(['data' => new PalletResource($pallet)]);
    }

    public function update(UpdatePalletRequest $request, string $id)
    {
        $pallet = Pallet::with('reception', 'boxes.box.productionInputs')->findOrFail($id);
        $this->authorize('update', $pallet);

        $error = PalletWriteService::validateUpdatePermissions($pallet);
        if ($error !== null) {
            return response()->json(['error' => $error], 403);
        }

        $updatedPallet = PalletWriteService::update($request, $pallet, $request->validated());

        return response()->json(new PalletResource($updatedPallet), 201);
    }

    public function destroy(string $id)
    {
        $pallet = Pallet::findOrFail($id);
        $this->authorize('delete', $pallet);
        if ($pallet->reception_id !== null) {
            return response()->json(['error' => 'No se puede eliminar un palet que proviene de una recepción. Elimine la recepción o modifique desde la recepción.'], 403);
        }
        PalletWriteService::destroy($pallet);

        return response()->json(['message' => 'Palet eliminado correctamente']);
    }

    public function destroyMultiple(DestroyMultiplePalletsRequest $request)
    {
        $palletIds = $request->validated('ids');
        $palletsWithReception = Pallet::whereIn('id', $palletIds)->whereNotNull('reception_id')->get();
        if ($palletsWithReception->isNotEmpty()) {
            $ids = $palletsWithReception->pluck('id')->implode(', ');

            return response()->json(['error' => "No se pueden eliminar palets que provienen de una recepción. Los siguientes palets pertenecen a una recepción: {$ids}."], 403);
        }
        PalletWriteService::destroyMultiple($palletIds);

        return response()->json(['message' => 'Palets eliminados correctamente']);
    }

    public function assignToPosition(AssignToPositionPalletRequest $request)
    {
        $v = $request->validated();
        PalletActionService::assignToPosition($v['position_id'], $v['pallet_ids']);

        return response()->json(['message' => 'Palets ubicados correctamente'], 200);
    }

    public function moveToStore(MoveToStorePalletRequest $request)
    {
        $v = $request->validated();
        $result = PalletActionService::moveToStore($v['pallet_id'], $v['store_id']);
        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json([
            'message' => 'Palet movido correctamente al nuevo almacén',
            'pallet' => new PalletResource($result['pallet']),
        ], 200);
    }

    public function moveMultipleToStore(MoveMultipleToStorePalletRequest $request)
    {
        $v = $request->validated();
        $result = PalletActionService::moveMultipleToStore($v['pallet_ids'], $v['store_id']);
        $response = [
            'message' => "Se movieron {$result['moved_count']} palet(s) correctamente al nuevo almacén",
            'moved_count' => $result['moved_count'],
            'total_count' => $result['total_count'],
        ];
        if (! empty($result['errors'])) {
            $response['errors'] = $result['errors'];
        }

        return response()->json($response, 200);
    }

    public function unassignPosition($id)
    {
        $pallet = Pallet::findOrFail($id);
        $this->authorize('update', $pallet);
        $stored = PalletActionService::unassignPosition((int) $id);
        if (! $stored) {
            return response()->json(['error' => 'El palet no está almacenado'], 404);
        }

        return response()->json(['message' => 'Posición eliminada correctamente del palet', 'pallet_id' => $id], 200);
    }

    public function bulkUpdateState(BulkUpdateStatePalletRequest $request)
    {
        $v = $request->validated();
        try {
            $updatedCount = PalletActionService::bulkUpdateState(
                (int) $v['status'],
                $v['ids'] ?? null,
                $v['filters'] ?? null,
                $request->boolean('applyToAll')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Palets actualizados correctamente', 'updated_count' => $updatedCount]);
    }

    public function linkOrder(LinkOrderPalletRequest $request, string $id)
    {
        $v = $request->validated();
        $result = PalletActionService::linkOrder((int) $id, $v['orderId']);
        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 400);
        }
        $message = $result['status'] === 'already_linked' ? 'El palet ya está vinculado a este pedido' : 'Palet vinculado correctamente al pedido';

        return response()->json([
            'message' => $message,
            'pallet_id' => $id,
            'order_id' => $v['orderId'],
            'pallet' => new PalletResource($result['pallet']),
        ], 200);
    }

    public function linkOrders(LinkOrdersPalletRequest $request)
    {
        $result = PalletActionService::linkOrders($request->validated('pallets'));
        $response = [
            'message' => 'Proceso de vinculación completado',
            'linked' => count(array_filter($result['results'], fn ($r) => ($r['status'] ?? '') === 'linked')),
            'already_linked' => count(array_filter($result['results'], fn ($r) => ($r['status'] ?? '') === 'already_linked')),
            'errors' => count($result['errors']),
            'results' => $result['results'],
        ];
        if (! empty($result['errors'])) {
            $response['errors_details'] = $result['errors'];
        }

        return response()->json($response, empty($result['errors']) ? 200 : 207);
    }

    public function unlinkOrder(string $id)
    {
        $pallet = Pallet::findOrFail($id);
        $this->authorize('update', $pallet);
        $result = PalletActionService::unlinkOrder((int) $id);
        $message = $result['status'] === 'already_unlinked' ? 'El palet ya no está asociado a ninguna orden' : 'Palet desvinculado correctamente de la orden';

        return response()->json([
            'message' => $message,
            'pallet_id' => $id,
            'order_id' => $result['order_id'] ?? null,
            'pallet' => new PalletResource($result['pallet']),
        ], 200);
    }

    public function unlinkOrders(UnlinkOrdersPalletRequest $request)
    {
        $result = PalletActionService::unlinkOrders($request->validated('pallet_ids'));
        $response = [
            'message' => 'Proceso de desvinculación completado',
            'unlinked' => count(array_filter($result['results'], fn ($r) => ($r['status'] ?? '') === 'unlinked')),
            'already_unlinked' => count(array_filter($result['results'], fn ($r) => ($r['status'] ?? '') === 'already_unlinked')),
            'errors' => count($result['errors']),
            'results' => $result['results'],
        ];
        if (! empty($result['errors'])) {
            $response['errors_details'] = $result['errors'];
        }

        return response()->json($response, empty($result['errors']) ? 200 : 207);
    }

    public function searchByLot(SearchByLotPalletRequest $request)
    {
        $data = PalletListService::searchByLot($request->validated('lot'));

        return response()->json(['data' => $data], 200);
    }

    public function registeredPallets()
    {
        $this->authorize('viewAny', Pallet::class);
        $data = PalletListService::registeredPallets();

        return response()->json($data, 200);
    }

    public function availableForOrder(AvailableForOrderPalletRequest $request, int $orderId)
    {
        $v = $request->validated();
        $result = PalletListService::availableForOrder(
            $v['orderId'],
            $v['ids'] ?? null,
            $v['storeId'] ?? null,
            $v['perPage'] ?? 20
        );

        return response()->json($result, 200);
    }
}
