<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\DeliveryRoute;
use App\Models\FieldOperator;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class RouteManagementApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private string $tenantSubdomain;
    private User $adminUser;
    private User $fieldUser;
    private FieldOperator $fieldOperator;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();

        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'routes-' . uniqid();
        Tenant::create([
            'name' => 'Routes Tenant',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $this->tenantSubdomain = $slug;
        $this->adminUser = User::create([
            'name' => 'Admin',
            'email' => $slug . '-admin@test.com',
            'role' => Role::Administrador->value,
            'active' => true,
        ]);
        $this->fieldUser = User::create([
            'name' => 'Field User',
            'email' => $slug . '-field@test.com',
            'role' => Role::RepartidorAutoventa->value,
            'active' => true,
        ]);
        $this->fieldOperator = FieldOperator::create([
            'name' => 'Repartidor Test',
            'user_id' => $this->fieldUser->id,
        ]);
    }

    private function headersFor(User $user): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_admin_can_create_template_and_route_and_field_user_can_close_stop(): void
    {
        $template = $this->withHeaders($this->headersFor($this->adminUser))
            ->postJson('/api/v2/route-templates', [
                'name' => 'Ruta Martes',
                'fieldOperatorId' => $this->fieldOperator->id,
                'stops' => [[
                    'position' => 1,
                    'stopType' => 'sugerida',
                    'targetType' => 'location',
                    'label' => 'Zona Puerto',
                    'address' => 'Muelle 1',
                    'notes' => 'Parada ligera',
                ]],
            ]);

        $template->assertStatus(201);
        $templateId = $template->json('data.id');

        $route = $this->withHeaders($this->headersFor($this->adminUser))
            ->postJson('/api/v2/routes', [
                'routeTemplateId' => $templateId,
                'name' => 'Ruta 2026-03-21',
                'routeDate' => now()->format('Y-m-d'),
                'fieldOperatorId' => $this->fieldOperator->id,
            ]);

        $route->assertStatus(201);
        $routeId = $route->json('data.id');
        $stopId = $route->json('data.stops.0.id');
        app('auth')->forgetGuards();
        $fieldHeaders = $this->headersFor($this->fieldUser);

        $fieldList = $this->getJson('/api/v2/field/routes', $fieldHeaders);

        $fieldList->assertStatus(200);
        $fieldList->assertJsonFragment(['id' => $routeId]);

        $stopUpdate = $this->putJson('/api/v2/field/routes/' . $routeId . '/stops/' . $stopId, [
                'status' => RouteStop::STATUS_COMPLETED,
                'result_type' => RouteStop::RESULT_TYPE_VISIT,
                'result_notes' => 'Visita realizada correctamente',
            ], $fieldHeaders);

        $stopUpdate->assertStatus(200);

        $this->assertDatabaseHas('route_stops', [
            'id' => $stopId,
            'status' => RouteStop::STATUS_COMPLETED,
            'result_type' => RouteStop::RESULT_TYPE_VISIT,
            'result_notes' => 'Visita realizada correctamente',
        ], 'tenant');
    }

    public function test_field_user_cannot_set_invalid_result_type_on_stop(): void
    {
        $route = DeliveryRoute::create([
            'name' => 'Ruta Invalid Result',
            'route_date' => now()->format('Y-m-d'),
            'status' => DeliveryRoute::STATUS_PLANNED,
            'field_operator_id' => $this->fieldOperator->id,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $stop = $route->stops()->create([
            'position' => 1,
            'stop_type' => RouteStop::STOP_TYPE_SUGGESTED,
            'target_type' => 'location',
            'label' => 'Parada test',
            'status' => RouteStop::STATUS_PENDING,
        ]);

        $response = $this->withHeaders($this->headersFor($this->fieldUser))
            ->putJson('/api/v2/field/routes/' . $route->id . '/stops/' . $stop->id, [
                'status' => RouteStop::STATUS_COMPLETED,
                'result_type' => 'cualquier_cosa',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['result_type']);
    }

    public function test_field_user_cannot_access_admin_route_crud(): void
    {
        $response = $this->withHeaders($this->headersFor($this->fieldUser))
            ->getJson('/api/v2/routes');

        $response->assertStatus(403);
    }
}
