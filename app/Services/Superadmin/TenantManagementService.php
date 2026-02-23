<?php

namespace App\Services\Superadmin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    public function changeStatus(Tenant $tenant, string $newStatus): Tenant
    {
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
     */
    public function getTenantUsers(Tenant $tenant): Collection
    {
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        return User::on('tenant')
            ->select('id', 'name', 'email', 'role', 'last_login_at', 'active')
            ->orderBy('name')
            ->get();
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

    public function invalidateCorsCache(string $subdomain): void
    {
        Cache::forget("cors:tenant:{$subdomain}");
    }
}
