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

    public function activate(Tenant $tenant): TenantResource|JsonResponse
    {
        return $this->attemptStatusChange($tenant, 'active');
    }

    public function suspend(Tenant $tenant): TenantResource|JsonResponse
    {
        return $this->attemptStatusChange($tenant, 'suspended');
    }

    public function cancel(Tenant $tenant): TenantResource|JsonResponse
    {
        return $this->attemptStatusChange($tenant, 'cancelled');
    }

    private function attemptStatusChange(Tenant $tenant, string $newStatus): TenantResource|JsonResponse
    {
        try {
            $updated = $this->service->changeStatus($tenant, $newStatus);

            return new TenantResource($updated);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'current_status' => $tenant->status,
                'requested_status' => $newStatus,
                'onboarding' => $this->onboardingPayload($tenant),
            ], 422);
        }
    }

    public function retryOnboarding(Tenant $tenant): JsonResponse
    {
        if ($tenant->onboarding_step >= \App\Services\Superadmin\TenantOnboardingService::TOTAL_STEPS) {
            return response()->json([
                'message' => 'El onboarding ya se completó.',
                'onboarding' => $this->onboardingPayload($tenant),
            ]);
        }

        $tenant->update([
            'status' => 'pending',
            'onboarding_error' => null,
            'onboarding_failed_at' => null,
        ]);

        OnboardTenantJob::dispatch($tenant->id);

        $tenant->refresh();

        return response()->json([
            'message' => 'Onboarding relanzado.',
            'onboarding' => $this->onboardingPayload($tenant),
        ]);
    }

    public function onboardingStatus(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $this->onboardingPayload($tenant),
        ]);
    }

    private function onboardingPayload(Tenant $tenant): array
    {
        return [
            'step' => $tenant->onboarding_step,
            'total_steps' => \App\Services\Superadmin\TenantOnboardingService::TOTAL_STEPS,
            'step_label' => $tenant->onboarding_step_label,
            'status' => $tenant->onboarding_status,
            'error' => $tenant->onboarding_error,
            'failed_at' => $tenant->onboarding_failed_at,
        ];
    }

    public function destroy(Request $request, Tenant $tenant): JsonResponse
    {
        if ($request->query('confirm_delete') !== 'true') {
            return response()->json([
                'message' => 'Debes confirmar la eliminación añadiendo ?confirm_delete=true',
                'tenant' => $tenant->subdomain,
                'status' => $tenant->status,
            ], 422);
        }

        try {
            $dropDb = $request->boolean('drop_database', false);
            $summary = $this->service->deleteTenant($tenant, $dropDb);

            return response()->json([
                'message' => 'Tenant eliminado correctamente.',
                'details' => $summary,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function tenantUsers(Tenant $tenant): AnonymousResourceCollection|JsonResponse
    {
        try {
            $users = $this->service->getTenantUsers($tenant);

            return TenantUserResource::collection($users);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'onboarding' => $this->onboardingPayload($tenant),
            ], 422);
        }
    }
}
