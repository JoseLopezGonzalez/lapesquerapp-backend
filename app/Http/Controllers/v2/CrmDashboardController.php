<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Services\v2\CrmDashboardService;
use Illuminate\Http\Request;

class CrmDashboardController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => CrmDashboardService::getData($request->user()),
        ]);
    }
}
