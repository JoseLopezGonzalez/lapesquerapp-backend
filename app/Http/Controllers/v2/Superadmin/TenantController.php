<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\Superadmin\StoreTenantRequest;
use App\Http\Requests\v2\Superadmin\UpdateTenantRequest;
use App\Http\Resources\v2\Superadmin\TenantResource;
use App\Http\Resources\v2\Superadmin\TenantUserResource;
use App\Jobs\OnboardTenantJob;
use App\Models\Tenant;
use App\Services\Superadmin\TenantManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    public function __construct(
        private TenantManagementService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenants = $this->service->list(
            $request->query('status'),
            $request->query('search'),
            (int) $request->query('per_page', 15)
        );

        return TenantResource::collection($tenants);
    }

    public function show(Tenant $tenant): TenantResource
    {
        return new TenantResource($tenant);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tenant = Tenant::create([
            'name' => $data['name'],
            'subdomain' => $data['subdomain'],
            'database' => 'tenant_' . $data['subdomain'],
            'status' => 'pending',
            'plan' => $data['plan'] ?? null,
            'timezone' => $data['timezone'] ?? 'Europe/Madrid',
            'branding_image_url' => $data['branding_image_url'] ?? null,
            'admin_email' => $data['admin_email'],
            'onboarding_step' => 0,
        ]);

        OnboardTenantJob::dispatch($tenant->id);

        return (new TenantResource($tenant))
            ->additional(['message' => 'Tenant creado. Onboarding en progreso.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        $updated = $this->service->updateTenant($tenant, $request->validated());

        return new TenantResource($updated);
    }

    public function activate(Tenant $tenant): TenantResource
    {
        $updated = $this->service->changeStatus($tenant, 'active');

        return new TenantResource($updated);
    }

    public function suspend(Tenant $tenant): TenantResource
    {
        $updated = $this->service->changeStatus($tenant, 'suspended');

        return new TenantResource($updated);
    }

    public function cancel(Tenant $tenant): TenantResource
    {
        $updated = $this->service->changeStatus($tenant, 'cancelled');

        return new TenantResource($updated);
    }

    public function retryOnboarding(Tenant $tenant): JsonResponse
    {
        if ($tenant->status !== 'pending' && $tenant->onboarding_step < 8) {
            $tenant->update(['status' => 'pending']);
        }

        OnboardTenantJob::dispatch($tenant->id);

        return response()->json([
            'message' => 'Onboarding relanzado.',
            'onboarding_step' => $tenant->onboarding_step,
        ]);
    }

    public function tenantUsers(Tenant $tenant): AnonymousResourceCollection
    {
        $users = $this->service->getTenantUsers($tenant);

        return TenantUserResource::collection($users);
    }
}
