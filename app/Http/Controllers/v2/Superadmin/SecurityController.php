<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantBlocklist;
use App\Services\Superadmin\TenantManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SecurityController extends Controller
{
    public function __construct(
        private TenantManagementService $service
    ) {}

    /**
     * List active Sanctum tokens for a tenant.
     */
    public function tokens(Tenant $tenant): JsonResponse
    {
        try {
            $tokens = $this->service->getActiveTokens($tenant);

            return response()->json([
                'data'  => $tokens,
                'total' => $tokens->count(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Revoke a specific token from a tenant.
     */
    public function revokeToken(Tenant $tenant, int $tokenId): JsonResponse
    {
        try {
            $deleted = $this->service->revokeToken($tenant, $tokenId);

            if (!$deleted) {
                return response()->json(['message' => 'Token no encontrado.'], 404);
            }

            return response()->json(['message' => 'Token revocado correctamente.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Revoke all tokens from a tenant.
     */
    public function revokeAllTokens(Tenant $tenant): JsonResponse
    {
        try {
            $count = $this->service->revokeAllTokens($tenant);

            return response()->json([
                'message'        => 'Todos los tokens han sido revocados.',
                'tokens_revoked' => $count,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Blocklist ─────────────────────────────────────────────────────────────

    /**
     * List active blocks for a tenant.
     */
    public function listBlocks(Tenant $tenant): JsonResponse
    {
        $blocks = TenantBlocklist::where('tenant_id', $tenant->id)
            ->with('blockedBy:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $blocks]);
    }

    /**
     * Add a new IP or email block for a tenant.
     */
    public function block(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'type'       => 'required|in:ip,email',
            'value'      => 'required|string|max:255',
            'reason'     => 'nullable|string|max:500',
            'expires_at' => 'nullable|date',
        ]);

        $block = TenantBlocklist::create([
            'tenant_id'                => $tenant->id,
            'type'                     => $request->type,
            'value'                    => $request->value,
            'blocked_by_superadmin_id' => $request->user()->id,
            'reason'                   => $request->reason,
            'expires_at'               => $request->expires_at,
        ]);

        $this->invalidateBlocklistCache($tenant->id, $request->value);

        return response()->json(['message' => 'Bloqueo creado.', 'data' => $block], 201);
    }

    /**
     * Remove a block.
     */
    public function unblock(Tenant $tenant, int $blockId): JsonResponse
    {
        $block = TenantBlocklist::where('tenant_id', $tenant->id)->findOrFail($blockId);

        $this->invalidateBlocklistCache($tenant->id, $block->value);

        $block->delete();

        return response()->json(['message' => 'Bloqueo eliminado.']);
    }

    private function invalidateBlocklistCache(int $tenantId, string $value): void
    {
        Cache::forget("blocklist:{$tenantId}:{$value}:*");
    }
}
