<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexProductionControlPanelRequest;
use App\Services\Production\ProductionControlPanelService;

class ProductionControlPanelController extends Controller
{
    public function __construct(
        private ProductionControlPanelService $service,
    ) {}

    public function index(IndexProductionControlPanelRequest $request)
    {
        $filters = $request->only([
            'lot', 'species_id', 'date_from', 'date_to',
            'reconciliation_status', 'per_page', 'sort_by', 'sort_dir',
        ]);

        $data = $this->service->getPanelData($filters);

        return response()->json([
            'message' => 'Panel de control de producciones obtenido correctamente.',
            'data'    => $data,
        ]);
    }
}
