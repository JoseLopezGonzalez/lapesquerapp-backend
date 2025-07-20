<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function showBySubdomain(string $subdomain): JsonResponse
    {
        $tenant = Tenant::where('subdomain', $subdomain)->first();

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        return response()->json([
            'active' => (bool) $tenant->active,
            'branding_image_url' => $tenant->branding_image_url,
            'name' => $tenant->name,
        ]);
    }
}
