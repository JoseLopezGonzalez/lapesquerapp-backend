<?php

namespace App\Services\Superadmin;

use App\Mail\ImpersonationRequestEmail;
use App\Models\ImpersonationLog;
use App\Models\ImpersonationRequest;
use App\Models\SuperadminUser;
use App\Models\Tenant;
use App\Models\User;
use App\Sanctum\SuperadminPersonalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class ImpersonationService
{
    /**
     * Create a consent-based impersonation request and send approval email.
     */
    public function requestConsent(SuperadminUser $superadmin, Tenant $tenant, int $targetUserId): ImpersonationRequest
    {
        $token = Str::random(64);

        $request = ImpersonationRequest::create([
            'superadmin_user_id' => $superadmin->id,
            'tenant_id' => $tenant->id,
            'target_user_id' => $targetUserId,
            'status' => 'pending',
            'token' => hash('sha256', $token),
        ]);

        $targetUser = $this->getTenantUser($tenant, $targetUserId);

        $approveUrl = URL::signedRoute('impersonation.approve', ['token' => $token]);
        $rejectUrl = URL::signedRoute('impersonation.reject', ['token' => $token]);

        Mail::to($targetUser->email)->send(
            new ImpersonationRequestEmail($tenant->name, $approveUrl, $rejectUrl)
        );

        return $request;
    }

    /**
     * Approve an impersonation request (called from signed URL).
     */
    public function approve(string $rawToken): ImpersonationRequest
    {
        $hashed = hash('sha256', $rawToken);
        $request = ImpersonationRequest::where('token', $hashed)->pending()->firstOrFail();

        $request->update([
            'status' => 'approved',
            'approved_at' => now('UTC'),
            'expires_at' => now('UTC')->addMinutes(30),
        ]);

        return $request;
    }

    /**
     * Reject an impersonation request (called from signed URL).
     */
    public function reject(string $rawToken): ImpersonationRequest
    {
        $hashed = hash('sha256', $rawToken);
        $request = ImpersonationRequest::where('token', $hashed)->pending()->firstOrFail();

        $request->update(['status' => 'rejected']);

        return $request;
    }

    /**
     * Generate an impersonation token for an approved consent request.
     */
    public function generateTokenFromRequest(SuperadminUser $superadmin, Tenant $tenant): array
    {
        $request = ImpersonationRequest::where('superadmin_user_id', $superadmin->id)
            ->where('tenant_id', $tenant->id)
            ->approved()
            ->where('expires_at', '>', now('UTC'))
            ->latest()
            ->firstOrFail();

        return $this->createImpersonationToken($superadmin, $tenant, $request->target_user_id, 'consent');
    }

    /**
     * Perform a silent impersonation (no consent, with audit log).
     */
    public function silentImpersonate(SuperadminUser $superadmin, Tenant $tenant, int $targetUserId): array
    {
        return $this->createImpersonationToken($superadmin, $tenant, $targetUserId, 'silent');
    }

    /**
     * End an impersonation session.
     */
    public function endSession(int $logId): void
    {
        $log = ImpersonationLog::findOrFail($logId);
        $log->update(['ended_at' => now('UTC')]);
    }

    private function createImpersonationToken(SuperadminUser $superadmin, Tenant $tenant, int $targetUserId, string $mode): array
    {
        $targetUser = $this->getTenantUser($tenant, $targetUserId);

        $token = $targetUser->createToken('impersonation', ['impersonation'])->plainTextToken;

        $log = ImpersonationLog::create([
            'superadmin_user_id' => $superadmin->id,
            'tenant_id' => $tenant->id,
            'target_user_id' => $targetUserId,
            'mode' => $mode,
            'started_at' => now('UTC'),
        ]);

        $redirectUrl = "https://{$tenant->subdomain}.lapesquerapp.es/auth/impersonate?token={$token}";

        return [
            'impersonation_token' => $token,
            'redirect_url' => $redirectUrl,
            'log_id' => $log->id,
        ];
    }

    private function getTenantUser(Tenant $tenant, int $userId): User
    {
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        /** @var User $user */
        $user = User::on('tenant')->findOrFail($userId);

        return $user;
    }
}
