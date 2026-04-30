<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\ApplyManualBoxCostsByLotProductRequest;
use App\Http\Requests\v2\ApplyManualBoxCostsByProductRequest;
use App\Http\Requests\v2\MissingSalesCostBoxesRequest;
use App\Http\Requests\v2\MissingStockCostBoxesRequest;
use App\Services\v2\CostRegularizationService;

class CostRegularizationController extends Controller
{
    public function __construct(private readonly CostRegularizationService $service) {}

    public function salesMissingCostBoxes(MissingSalesCostBoxesRequest $request)
    {
        return response()->json(
            $this->service->salesMissingCostBoxes($request->validated())
        );
    }

    public function stockMissingCostBoxes(MissingStockCostBoxesRequest $request)
    {
        return response()->json(
            $this->service->stockMissingCostBoxes($request->validated())
        );
    }

    public function applyManualCostsByProduct(ApplyManualBoxCostsByProductRequest $request)
    {
        return response()->json(
            $this->service->applyManualCostsByProduct($request->validated())
        );
    }

    public function applyManualCostsByLotProduct(ApplyManualBoxCostsByLotProductRequest $request)
    {
        return response()->json(
            $this->service->applyManualCostsByLotProduct($request->validated())
        );
    }
}
