<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Services\v2\CrmDashboardService;
use Illuminate\Http\Request;

class CrmDashboardController extends Controller
{
    public function pendingActions(Request $request)
    {
        return response()->json([
            'data' => CrmDashboardService::getPendingActionsData($request->user()),
        ]);
    }

    public function customers(Request $request)
    {
        return response()->json([
            'data' => CrmDashboardService::getCustomersData($request->user()),
        ]);
    }

    public function prospects(Request $request)
    {
        return response()->json([
            'data' => CrmDashboardService::getProspectsData($request->user()),
        ]);
    }
}
