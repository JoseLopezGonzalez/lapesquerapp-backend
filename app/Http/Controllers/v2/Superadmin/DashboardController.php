<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\Superadmin\TenantManagementService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private TenantManagementService $service
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->getDashboardStats());
    }
}
