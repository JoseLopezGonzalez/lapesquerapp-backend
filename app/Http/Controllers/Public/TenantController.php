<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ShowTenantBySubdomainRequest;
use App\Http\Resources\Public\TenantPublicResource;
use App\Http\Support\CorsResponse;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TenantController extends Controller
{
    /**
     * Respuesta a OPTIONS (preflight CORS). Laravel/HandleCors suele gestionarla;
     * esta ruta asegura CORS en el endpoint público aunque el middleware falle.
     */
    public function optionsTenant(): Response
    {
        return CorsResponse::preflightResponse(request());
    }

    /** @return JsonResponse|SymfonyResponse */
    public function showBySubdomain(ShowTenantBySubdomainRequest $request)
    {
        $tenant = Tenant::where('subdomain', $request->validated('subdomain'))->first();

        if (! $tenant) {
            $response = response()->json([
                'error' => 'Tenant no encontrado',
            ], 404);
            return CorsResponse::addToResponse($request, $response);
        }

        $response = (new TenantPublicResource($tenant))->response();
        return CorsResponse::addToResponse($request, $response);
    }
}
