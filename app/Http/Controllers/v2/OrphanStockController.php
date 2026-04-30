<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexOrphanStockRequest;
use App\Services\Production\OrphanStockService;

class OrphanStockController extends Controller
{
    public function __construct(
        private OrphanStockService $service,
    ) {}

    public function index(IndexOrphanStockRequest $request)
    {
        $filters = $request->only(['lot', 'per_page', 'sort_dir']);
        $filters['page'] = $request->integer('page', 1);

        $data = $this->service->getLots($filters);

        return response()->json([
            'message' => 'Lotes huerfanos en stock obtenidos correctamente.',
            'data'    => $data,
        ]);
    }
}
