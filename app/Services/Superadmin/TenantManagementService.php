<?php

namespace App\Services\Superadmin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantManagementService
{
    public function list(?string $status, ?string $search, int $perPage = 15): LengthAwarePaginator
    {
        $query = Tenant::query()->orderByDesc('created_at');

        if ($status) {
            $query->byStatus($status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Transiciones válidas de estado.
     * - pending: solo puede pasar a cancelled (onboarding lo activa automáticamente al completarse).
     * - active: puede suspenderse o cancelarse.
     * - suspended: puede reactivarse o cancelarse.
     * - cancelled: puede reactivarse (solo si completó onboarding).
     */
    private const ALLOWED_TRANSITIONS = [
        'pending'   => ['cancelled'],
        'active'    => ['suspended', 'cancelled'],
        'suspended' => ['active', 'cancelled'],
        'cancelled' => ['active'],
    ];

    /**
     * @throws \InvalidArgumentException si la transición no es válida.
     */
    public function changeStatus(Tenant $tenant, string $newStatus): Tenant
    {
        $current = $tenant->status ?? 'pending';
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new \InvalidArgumentException(
                "No se puede cambiar de '{$current}' a '{$newStatus}'."
            );
        }

        $onboardingComplete = ($tenant->onboarding_step ?? 0) >= TenantOnboardingService::TOTAL_STEPS;

        if ($newStatus === 'active' && !$onboardingComplete) {
            throw new \InvalidArgumentException(
                "No se puede activar un tenant que no ha completado el onboarding (paso {$tenant->onboarding_step}/" . TenantOnboardingService::TOTAL_STEPS . ').'
            );
        }

        $tenant->update(['status' => $newStatus]);

        $this->invalidateCorsCache($tenant->subdomain);

        return $tenant->fresh();
    }

    public function updateTenant(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh();
    }

    /**
     * Read users from a tenant's database (cross-tenant read).
     *
     * @throws \InvalidArgumentException if tenant DB is not ready.
     */
    public function getTenantUsers(Tenant $tenant): Collection
    {
        $this->ensureTenantDatabaseReady($tenant);

        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        return User::on('tenant')
            ->select('id', 'name', 'email', 'role', 'active', 'created_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * List active Sanctum tokens for a tenant's users (cross-tenant read).
     *
     * @throws \InvalidArgumentException if tenant DB is not ready.
     */
    public function getActiveTokens(Tenant $tenant): Collection
    {
        $this->ensureTenantDatabaseReady($tenant);

        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        return DB::connection('tenant')
            ->table('personal_access_tokens')
            ->select('id', 'tokenable_id', 'name', 'abilities', 'last_used_at', 'created_at', 'expires_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Revoke a specific token from a tenant's DB.
     *
     * @throws \InvalidArgumentException if tenant DB is not ready.
     */
    public function revokeToken(Tenant $tenant, int $tokenId): bool
    {
        $this->ensureTenantDatabaseReady($tenant);

        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        $deleted = DB::connection('tenant')
            ->table('personal_access_tokens')
            ->where('id', $tokenId)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Revoke all tokens from a tenant's DB.
     *
     * @throws \InvalidArgumentException if tenant DB is not ready.
     */
    public function revokeAllTokens(Tenant $tenant): int
    {
        $this->ensureTenantDatabaseReady($tenant);

        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        return DB::connection('tenant')
            ->table('personal_access_tokens')
            ->delete();
    }

    /**
     * Guard: throw if the tenant's database is not provisioned yet.
     */
    public function ensureTenantDatabaseReady(Tenant $tenant): void
    {
        $minStep = 5; // step 5 = admin user created → DB, migrations, seeder done

        if (($tenant->onboarding_step ?? 0) < $minStep) {
            throw new \InvalidArgumentException(
                "La base de datos del tenant '{$tenant->subdomain}' aún no está disponible. "
                . "Onboarding en paso {$tenant->onboarding_step}/" . TenantOnboardingService::TOTAL_STEPS . '.'
            );
        }
    }

    public function getDashboardStats(): array
    {
        $counts = Tenant::query()
            ->selectRaw("status, COUNT(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $lastOnboarding = Tenant::whereNotNull('onboarding_step')
            ->orderByDesc('created_at')
            ->first();

        return [
            'total' => array_sum($counts),
            'active' => $counts['active'] ?? 0,
            'suspended' => $counts['suspended'] ?? 0,
            'pending' => $counts['pending'] ?? 0,
            'cancelled' => $counts['cancelled'] ?? 0,
            'last_onboarding' => $lastOnboarding ? [
                'id' => $lastOnboarding->id,
                'name' => $lastOnboarding->name,
                'subdomain' => $lastOnboarding->subdomain,
                'onboarding_step' => $lastOnboarding->onboarding_step,
                'created_at' => $lastOnboarding->created_at,
            ] : null,
        ];
    }

    /**
     * Delete a tenant and optionally drop its database.
     *
     * @throws \InvalidArgumentException if the tenant is not in a deletable state.
     */
    public function deleteTenant(Tenant $tenant, bool $dropDatabase = false): array
    {
        $deletableStatuses = ['cancelled', 'pending'];

        if (!in_array($tenant->status, $deletableStatuses, true)) {
            throw new \InvalidArgumentException(
                "Solo se pueden eliminar tenants en estado 'cancelled' o 'pending'. "
                . "Estado actual: '{$tenant->status}'. Cancela el tenant primero."
            );
        }

        $summary = [
            'tenant' => $tenant->subdomain,
            'database_dropped' => false,
        ];

        if ($dropDatabase && $tenant->database) {
            $dbName = $tenant->database;

            $exists = DB::connection('mysql')
                ->select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$dbName]);

            if (!empty($exists)) {
                DB::connection('mysql')->statement("DROP DATABASE `{$dbName}`");
                $summary['database_dropped'] = true;

                Log::info("Tenant [{$tenant->subdomain}] database '{$dbName}' dropped.");
            }
        }

        $this->invalidateCorsCache($tenant->subdomain);

        Log::info("Tenant [{$tenant->subdomain}] (ID {$tenant->id}) deleted.", $summary);

        $tenant->delete();

        return $summary;
    }

    public function invalidateCorsCache(string $subdomain): void
    {
        Cache::forget("cors:tenant:{$subdomain}");
    }
}
