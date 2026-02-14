<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\BulkUpdateDeclaredDataRequest;
use App\Http\Requests\v2\DestroyMultipleRawMaterialReceptionsRequest;
use App\Http\Requests\v2\IndexRawMaterialReceptionRequest;
use App\Http\Requests\v2\StoreRawMaterialReceptionRequest;
use App\Http\Requests\v2\UpdateRawMaterialReceptionRequest;
use App\Http\Requests\v2\ValidateBulkUpdateDeclaredDataRequest;
use App\Http\Resources\v2\RawMaterialReceptionResource;
use App\Services\v2\RawMaterialReceptionBulkService;
use App\Services\v2\RawMaterialReceptionListService;
use App\Services\v2\RawMaterialReceptionWriteService;
use App\Models\RawMaterialReception;
use Illuminate\Support\Facades\DB;

class RawMaterialReceptionController extends Controller
{
    public function index(IndexRawMaterialReceptionRequest $request)
    {
        return RawMaterialReceptionResource::collection(
            RawMaterialReceptionListService::list($request)
        );
    }

    public function store(StoreRawMaterialReceptionRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $reception = RawMaterialReceptionWriteService::store($request->validated());
            $reception->load('supplier', 'products.product', 'pallets.reception', 'pallets.boxes.box.productionInputs');

            return response()->json([
                'message' => 'Recepción de materia prima creada correctamente.',
                'data' => new RawMaterialReceptionResource($reception),
            ], 201);
        });
    }

    public function show($id)
    {
        $reception = RawMaterialReception::with('supplier', 'products.product', 'pallets.reception', 'pallets.boxes.box.productionInputs')->findOrFail($id);
        $this->authorize('view', $reception);
        return response()->json([
            'data' => new RawMaterialReceptionResource($reception),
        ]);
    }

    public function update(UpdateRawMaterialReceptionRequest $request, $id)
    {
        $reception = RawMaterialReception::with('pallets.reception', 'pallets.boxes.box.productionInputs')->findOrFail($id);
        $this->authorize('update', $reception);

        return DB::transaction(function () use ($reception, $request) {
            RawMaterialReceptionWriteService::update($reception, $request->validated());
            $reception->load('supplier', 'products.product', 'pallets.reception', 'pallets.boxes.box.productionInputs');

            return response()->json([
                'message' => 'Recepción de materia prima actualizada correctamente.',
                'data' => new RawMaterialReceptionResource($reception),
            ]);
        });
    }

    public function destroy($id)
    {
        $reception = RawMaterialReception::findOrFail($id);
        $this->authorize('delete', $reception);
        $reception->delete();
        return response()->json(['message' => 'Recepción eliminada correctamente'], 200);
    }

    public function destroyMultiple(DestroyMultipleRawMaterialReceptionsRequest $request)
    {
        $ids = $request->validated('ids');

        $deleted = 0;
        $errors = [];

        foreach ($ids as $id) {
            $reception = RawMaterialReception::find($id);
            if (!$reception) {
                continue;
            }
            try {
                $reception->delete();
                $deleted++;
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $id,
                    'message' => $e->getMessage(),
                    'userMessage' => 'No se pudo eliminar la recepción #' . $id . ': ' . ($e->getMessage()),
                ];
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Algunas recepciones no pudieron eliminarse.',
                'userMessage' => count($errors) . ' recepción(es) no pudieron eliminarse (palets vinculados a pedidos, cajas en producción o almacenados).',
                'deleted' => $deleted,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'message' => 'Recepciones de materia prima eliminadas con éxito',
            'deleted' => $deleted,
        ]);
    }

    /**
     * Validar y previsualizar actualización de datos declarados de múltiples recepciones.
     * Este endpoint solo valida sin hacer cambios, permitiendo al frontend mostrar preview.
     */
    public function validateBulkUpdateDeclaredData(ValidateBulkUpdateDeclaredDataRequest $request)
    {
        $response = RawMaterialReceptionBulkService::validateBulkUpdateDeclaredData(
            $request->validated('receptions')
        );

        $statusCode = empty($response['errors_details'] ?? []) ? 200 : 207;

        return response()->json($response, $statusCode);
    }

    /**
     * Actualizar datos declarados de múltiples recepciones de forma masiva.
     */
    public function bulkUpdateDeclaredData(BulkUpdateDeclaredDataRequest $request)
    {
        $receptions = collect($request->validated('receptions'))->map(function (array $r): array {
            return [
                'supplier_id' => (int) $r['supplier_id'],
                'date' => $r['date'],
                'declared_total_amount' => isset($r['declared_total_amount']) ? (float) $r['declared_total_amount'] : null,
                'declared_total_net_weight' => isset($r['declared_total_net_weight']) ? (float) $r['declared_total_net_weight'] : null,
            ];
        })->all();

        $response = RawMaterialReceptionBulkService::bulkUpdateDeclaredData($receptions);

        $statusCode = ($response['errors'] ?? 0) === 0 ? 200 : 207;

        return response()->json($response, $statusCode);
    }
}
