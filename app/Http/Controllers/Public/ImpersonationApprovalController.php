<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Superadmin\ImpersonationService;
use Illuminate\Http\Request;

class ImpersonationApprovalController extends Controller
{
    public function __construct(
        private ImpersonationService $service
    ) {}

    /**
     * Approve an impersonation request via signed URL.
     */
    public function approve(Request $request, string $token)
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['error' => 'Enlace inválido o expirado.'], 403);
        }

        try {
            $this->service->approve($token);

            return response()->view('impersonation.approved', [], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Solicitud no encontrada o ya procesada.'], 404);
        }
    }

    /**
     * Reject an impersonation request via signed URL.
     */
    public function reject(Request $request, string $token)
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['error' => 'Enlace inválido o expirado.'], 403);
        }

        try {
            $this->service->reject($token);

            return response()->view('impersonation.rejected', [], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Solicitud no encontrada o ya procesada.'], 404);
        }
    }
}
