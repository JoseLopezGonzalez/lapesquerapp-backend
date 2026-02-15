<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ShowTenantBySubdomainRequest;
use App\Http\Resources\Public\TenantPublicResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function showBySubdomain(ShowTenantBySubdomainRequest $request): JsonResponse
    {
        $tenant = Tenant::where('subdomain', $request->validated('subdomain'))->first();

        if (! $tenant) {
            return response()->json([
                'error' => 'Tenant no encontrado',
            ], 404);
        }

        return (new TenantPublicResource($tenant))->response();
    }
}
