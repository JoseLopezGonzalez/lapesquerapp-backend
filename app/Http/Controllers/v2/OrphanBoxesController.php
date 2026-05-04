<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexOrphanBoxesRequest;
use App\Services\Production\OrphanBoxesService;

class OrphanBoxesController extends Controller
{
    public function __construct(
        private OrphanBoxesService $service,
    ) {}

    public function index(IndexOrphanBoxesRequest $request)
    {
        $filters = $request->only([
            'lot', 'article_id', 'per_page', 'sort_by', 'sort_dir',
        ]);

        $data = $this->service->getPaginated($filters);

        return response()->json([
            'message' => 'Listado de cajas sin palet obtenido correctamente.',
            'data' => $data,
        ]);
    }
}
