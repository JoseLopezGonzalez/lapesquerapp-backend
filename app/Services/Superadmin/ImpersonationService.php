<?php

namespace App\Services\Superadmin;

use App\Mail\ImpersonationRequestEmail;
use App\Models\ImpersonationLog;
use App\Models\ImpersonationRequest;
use App\Models\SuperadminUser;
use App\Models\Tenant;
use App\Models\User;
use App\Sanctum\SuperadminPersonalAccessToken;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class ImpersonationService
{
    /**
     * Create a consent-based impersonation request and send approval email.
     */
    public function requestConsent(SuperadminUser $superadmin, Tenant $tenant, int $targetUserId, ?string $reason = null): ImpersonationRequest
    {
        $token = Str::random(64);

        $request = ImpersonationRequest::create([
            'superadmin_user_id' => $superadmin->id,
            'tenant_id'          => $tenant->id,
            'target_user_id'     => $targetUserId,
            'status'             => 'pending',
            'token'              => hash('sha256', $token),
        ]);

        $targetUser = $this->getTenantUser($tenant, $targetUserId);

        $approveUrl = URL::signedRoute('impersonation.approve', ['token' => $token]);
        $rejectUrl  = URL::signedRoute('impersonation.reject', ['token' => $token]);

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
        $hashed  = hash('sha256', $rawToken);
        $request = ImpersonationRequest::where('token', $hashed)->pending()->firstOrFail();

        $request->update([
            'status'      => 'approved',
            'approved_at' => now('UTC'),
            'expires_at'  => now('UTC')->addMinutes(30),
        ]);

        return $request;
    }

    /**
     * Reject an impersonation request (called from signed URL).
     */
    public function reject(string $rawToken): ImpersonationRequest
    {
        $hashed  = hash('sha256', $rawToken);
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
    public function silentImpersonate(SuperadminUser $superadmin, Tenant $tenant, int $targetUserId, string $reason): array
    {
        return $this->createImpersonationToken($superadmin, $tenant, $targetUserId, 'silent', $reason);
    }

    /**
     * End an impersonation session: set ended_at and revoke the impersonation token.
     */
    public function endSession(int $logId): void
    {
        $log = ImpersonationLog::findOrFail($logId);

        if ($log->ended_at) {
            return;
        }

        $log->update(['ended_at' => now('UTC')]);

        $tenant = Tenant::findOrFail($log->tenant_id);
        $this->connectToTenantDb($tenant);

        PersonalAccessToken::on('tenant')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $log->target_user_id)
            ->where('name', 'impersonation')
            ->whereNull('deleted_at')
            ->delete();
    }

    /**
     * Paginated history of impersonation sessions.
     */
    public function getHistory(
        ?int $tenantId,
        ?int $superadminUserId,
        ?string $from,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = ImpersonationLog::with(['superadminUser', 'tenant'])
            ->orderByDesc('started_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($superadminUserId) {
            $query->where('superadmin_user_id', $superadminUserId);
        }

        if ($from) {
            $query->where('started_at', '>=', $from);
        }

        return $query->paginate($perPage);
    }

    /**
     * Active impersonation sessions (started in last 2h and not ended).
     */
    public function getActiveSessions(): Collection
    {
        return ImpersonationLog::with(['superadminUser', 'tenant'])
            ->whereNull('ended_at')
            ->where('started_at', '>=', now('UTC')->subHours(2))
            ->orderByDesc('started_at')
            ->get();
    }

    private function createImpersonationToken(
        SuperadminUser $superadmin,
        Tenant $tenant,
        int $targetUserId,
        string $mode,
        ?string $reason = null
    ): array {
        $targetUser = $this->getTenantUser($tenant, $targetUserId);

        $token = $targetUser->createToken('impersonation', ['impersonation'])->plainTextToken;

        $log = ImpersonationLog::create([
            'superadmin_user_id' => $superadmin->id,
            'tenant_id'          => $tenant->id,
            'target_user_id'     => $targetUserId,
            'mode'               => $mode,
            'reason'             => $reason,
            'started_at'         => now('UTC'),
        ]);

        $redirectUrl = "https://{$tenant->subdomain}.lapesquerapp.es/auth/impersonate?token={$token}";

        return [
            'impersonation_token' => $token,
            'redirect_url'        => $redirectUrl,
            'log_id'              => $log->id,
        ];
    }

    private function getTenantUser(Tenant $tenant, int $userId): User
    {
        $this->connectToTenantDb($tenant);

        /** @var User $user */
        $user = User::on('tenant')->findOrFail($userId);

        return $user;
    }

    private function connectToTenantDb(Tenant $tenant): void
    {
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }
}
