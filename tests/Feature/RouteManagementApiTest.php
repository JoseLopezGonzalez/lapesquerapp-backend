<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\DeliveryRoute;
use App\Models\FieldOperator;
use App\Models\RouteStop;
use App\Models\RouteTemplate;
use App\Models\Salesperson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsRouteScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class RouteManagementApiTest extends TestCase
{
    use BuildsRouteScenario;
    use ConfiguresTenantConnection;
    use RefreshDatabase;

    private string $tenantSubdomain;

    private User $adminUser;

    private User $fieldUser;

    private User $commercialUser;

    private FieldOperator $fieldOperator;

    private Salesperson $commercialSalesperson;

    private Salesperson $otherSalesperson;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $slug = 'routes-'.uniqid();
        $scenario = $this->createRouteScenario($slug);

        $this->tenantSubdomain = $scenario['slug'];
        $this->adminUser = $scenario['adminUser'];
        $this->fieldUser = $scenario['fieldUser'];
        $this->commercialUser = $scenario['commercialUser'];
        $this->fieldOperator = $scenario['fieldOperator'];
        $this->commercialSalesperson = $scenario['commercialSalesperson'];
        $this->otherSalesperson = $scenario['otherSalesperson'];
    }

    private function headersFor(User $user): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
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

        $stopUpdate = $this->putJson('/api/v2/field/routes/'.$routeId.'/stops/'.$stopId, [
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
            ->putJson('/api/v2/field/routes/'.$route->id.'/stops/'.$stop->id, [
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

    public function test_commercial_is_scoped_to_own_routes_and_templates(): void
    {
        $ownTemplate = RouteTemplate::create([
            'name' => 'Template propio',
            'salesperson_id' => $this->commercialSalesperson->id,
            'created_by_user_id' => $this->commercialUser->id,
            'is_active' => true,
        ]);
        $foreignTemplate = RouteTemplate::create([
            'name' => 'Template ajeno',
            'salesperson_id' => $this->otherSalesperson->id,
            'created_by_user_id' => $this->adminUser->id,
            'is_active' => true,
        ]);

        $ownRoute = DeliveryRoute::create([
            'name' => 'Ruta propia',
            'route_date' => now()->format('Y-m-d'),
            'status' => DeliveryRoute::STATUS_PLANNED,
            'salesperson_id' => $this->commercialSalesperson->id,
            'created_by_user_id' => $this->commercialUser->id,
        ]);
        $foreignRoute = DeliveryRoute::create([
            'name' => 'Ruta ajena',
            'route_date' => now()->format('Y-m-d'),
            'status' => DeliveryRoute::STATUS_PLANNED,
            'salesperson_id' => $this->otherSalesperson->id,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->withHeaders($this->headersFor($this->commercialUser))
            ->getJson('/api/v2/routes')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $ownRoute->id, 'name' => 'Ruta propia'])
            ->assertJsonMissing(['id' => $foreignRoute->id, 'name' => 'Ruta ajena']);

        $this->withHeaders($this->headersFor($this->commercialUser))
            ->getJson('/api/v2/routes/'.$foreignRoute->id)
            ->assertStatus(403);

        $this->withHeaders($this->headersFor($this->commercialUser))
            ->getJson('/api/v2/route-templates')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $ownTemplate->id, 'name' => 'Template propio'])
            ->assertJsonMissing(['id' => $foreignTemplate->id, 'name' => 'Template ajeno']);

        $this->withHeaders($this->headersFor($this->commercialUser))
            ->getJson('/api/v2/route-templates/'.$foreignTemplate->id)
            ->assertStatus(403);
    }

    public function test_commercial_cannot_assign_routes_or_templates_to_other_salespeople(): void
    {
        $templateResponse = $this->withHeaders($this->headersFor($this->commercialUser))
            ->postJson('/api/v2/route-templates', [
                'name' => 'Template comercial',
                'salespersonId' => $this->otherSalesperson->id,
            ]);

        $templateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['salespersonId']);

        $routeResponse = $this->withHeaders($this->headersFor($this->commercialUser))
            ->postJson('/api/v2/routes', [
                'name' => 'Ruta comercial',
                'routeDate' => now()->format('Y-m-d'),
                'salespersonId' => $this->otherSalesperson->id,
            ]);

        $routeResponse->assertStatus(422)
            ->assertJsonValidationErrors(['salespersonId']);

        $ownTemplate = $this->withHeaders($this->headersFor($this->commercialUser))
            ->postJson('/api/v2/route-templates', [
                'name' => 'Template propio autocompletado',
            ]);

        $ownTemplate->assertStatus(201)
            ->assertJsonPath('data.salesperson.id', $this->commercialSalesperson->id);
    }

    public function test_route_scenario_helper_creates_reusable_base_graph(): void
    {
        $scenario = $this->createRouteScenario('routes-helper-'.uniqid());

        $this->assertNotNull($scenario['customer']->id);
        $this->assertNotNull($scenario['prospect']->id);
        $this->assertNotNull($scenario['order']->id);
        $this->assertSame($scenario['route']->id, $scenario['order']->route_id);
        $this->assertSame($scenario['routeStop']->id, $scenario['order']->route_stop_id);
        $this->assertSame($scenario['template']->id, $scenario['route']->route_template_id);
    }
}
