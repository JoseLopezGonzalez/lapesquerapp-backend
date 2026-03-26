<?php

namespace Database\Seeders;

use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\RouteTemplate;
use Database\Seeders\Concerns\SeedsTenantRoutesData;
use Illuminate\Database\Seeder;

class TenantDeliveryRoutesSeeder extends Seeder
{
    use SeedsTenantRoutesData;

    public function run(): void
    {
        $admin = $this->routesAdminUser();
        $fieldOperator = $this->routesFieldOperator();
        $primarySalesperson = $this->routesPrimarySalesperson();
        $secondarySalesperson = $this->routesSecondarySalesperson();

        $mercatiTirreno = $this->routesCustomer('Mercati Tirreno', $fieldOperator->id, $primarySalesperson->id);
        $reteMare = $this->routesCustomer('Rete Mare Milano', null, $primarySalesperson->id);
        $pescaTrieste = $this->routesCustomer('Pesca Trieste', $fieldOperator->id, $secondarySalesperson->id);
        $clienteOperativo = $this->routesCustomer('Cliente Ruta Operativa', $fieldOperator->id, null);

        $blufresco = $this->routesProspect('BluFresco Roma', $primarySalesperson->id);
        $blueHarbor = $this->routesProspect('BlueHarbor Export', $primarySalesperson->id);
        $marex = $this->routesProspect('Marex Torino', $secondarySalesperson->id);
        $laguna = $this->routesProspect('Laguna Select', $secondarySalesperson->id);

        $orders = [
            'ROUTE-001' => $this->routesOrder('ROUTE-001', $mercatiTirreno, $fieldOperator->id, $primarySalesperson->id),
            'ROUTE-002' => $this->routesOrder('ROUTE-002', $reteMare, null, $primarySalesperson->id),
            'ROUTE-003' => $this->routesOrder('ROUTE-003', $pescaTrieste, $fieldOperator->id, $secondarySalesperson->id),
            'ROUTE-004' => $this->routesOrder('ROUTE-004', $clienteOperativo, $fieldOperator->id, null),
        ];

        $templates = RouteTemplate::query()->whereIn('name', [
            'Ruta Admin General',
            'Ruta Comercial Norte',
            'Ruta Comercial Sur',
            'Ruta Comercial Secundario',
        ])->get()->keyBy('name');

        $routes = [
            [
                'name' => 'Ruta Comercial Norte - Hoy',
                'route_template_id' => $templates['Ruta Comercial Norte']->id ?? null,
                'description' => 'Ruta principal del comercial principal.',
                'route_date' => now()->format('Y-m-d'),
                'status' => DeliveryRoute::STATUS_PLANNED,
                'salesperson_id' => $primarySalesperson->id,
                'field_operator_id' => null,
                'created_by_user_id' => $primarySalesperson->user_id ?? $admin->id,
                'stops' => [
                    ['position' => 1, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $mercatiTirreno->id, 'label' => $mercatiTirreno->name, 'address' => $mercatiTirreno->shipping_address, 'notes' => 'Entrega planificada', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 2, 'stop_type' => RouteStop::STOP_TYPE_SUGGESTED, 'target_type' => 'prospect', 'prospect_id' => $blufresco->id, 'label' => $blufresco->company_name, 'address' => $blufresco->address, 'notes' => 'Visita comercial programada', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 3, 'stop_type' => RouteStop::STOP_TYPE_OPPORTUNITY, 'target_type' => 'location', 'label' => 'Mercado auxiliar Norte', 'address' => 'Puesto 12, mercado auxiliar Norte', 'notes' => 'Ventana comercial', 'status' => RouteStop::STATUS_PENDING],
                ],
            ],
            [
                'name' => 'Ruta Comercial Norte - En curso',
                'route_template_id' => $templates['Ruta Comercial Sur']->id ?? null,
                'description' => 'Ruta ya en ejecución con mezcla de stops.',
                'route_date' => now()->subDay()->format('Y-m-d'),
                'status' => DeliveryRoute::STATUS_IN_PROGRESS,
                'salesperson_id' => $primarySalesperson->id,
                'field_operator_id' => $fieldOperator->id,
                'created_by_user_id' => $primarySalesperson->user_id ?? $admin->id,
                'stops' => [
                    ['position' => 1, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $reteMare->id, 'label' => $reteMare->name, 'address' => $reteMare->shipping_address, 'notes' => 'Pedido premium en curso', 'status' => RouteStop::STATUS_COMPLETED, 'result_type' => RouteStop::RESULT_TYPE_DELIVERY, 'result_notes' => 'Entrega parcial completada', 'completed_at' => now()->subHours(3)],
                    ['position' => 2, 'stop_type' => RouteStop::STOP_TYPE_SUGGESTED, 'target_type' => 'prospect', 'prospect_id' => $blueHarbor->id, 'label' => $blueHarbor->company_name, 'address' => $blueHarbor->address, 'notes' => 'Pendiente de visita', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 3, 'stop_type' => RouteStop::STOP_TYPE_OPPORTUNITY, 'target_type' => 'location', 'label' => 'Depósito Sur', 'address' => 'Zona logística Sur 8', 'notes' => 'Recogida de documentación', 'status' => RouteStop::STATUS_COMPLETED, 'result_type' => RouteStop::RESULT_TYPE_VISIT, 'result_notes' => 'Documentación recogida', 'completed_at' => now()->subHours(1)],
                ],
            ],
            [
                'name' => 'Ruta Comercial Norte - Cerrada',
                'route_template_id' => null,
                'description' => 'Ruta comercial cerrada manualmente.',
                'route_date' => now()->subDays(4)->format('Y-m-d'),
                'status' => DeliveryRoute::STATUS_COMPLETED,
                'salesperson_id' => $primarySalesperson->id,
                'field_operator_id' => null,
                'created_by_user_id' => $admin->id,
                'stops' => [
                    ['position' => 1, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $mercatiTirreno->id, 'label' => $mercatiTirreno->name, 'address' => $mercatiTirreno->shipping_address, 'notes' => 'Entrega histórica', 'status' => RouteStop::STATUS_COMPLETED, 'result_type' => RouteStop::RESULT_TYPE_DELIVERY, 'result_notes' => 'Entrega completada sin incidencias', 'completed_at' => now()->subDays(4)->addHours(2)],
                    ['position' => 2, 'stop_type' => RouteStop::STOP_TYPE_SUGGESTED, 'target_type' => 'prospect', 'prospect_id' => $blufresco->id, 'label' => $blufresco->company_name, 'address' => $blufresco->address, 'notes' => 'Visita cerrada', 'status' => RouteStop::STATUS_COMPLETED, 'result_type' => RouteStop::RESULT_TYPE_VISIT, 'result_notes' => 'Interés confirmado', 'completed_at' => now()->subDays(4)->addHours(4)],
                    ['position' => 3, 'stop_type' => RouteStop::STOP_TYPE_OPPORTUNITY, 'target_type' => 'location', 'label' => 'Mercado satélite', 'address' => 'Mercado satélite 2', 'notes' => 'Parada libre', 'status' => RouteStop::STATUS_SKIPPED, 'result_type' => RouteStop::RESULT_TYPE_NO_CONTACT, 'result_notes' => 'No se encontró interlocutor', 'completed_at' => now()->subDays(4)->addHours(5)],
                ],
            ],
            [
                'name' => 'Ruta Comercial Secundario - Hoy',
                'route_template_id' => $templates['Ruta Comercial Secundario']->id ?? null,
                'description' => 'Ruta principal del comercial secundario.',
                'route_date' => now()->addDay()->format('Y-m-d'),
                'status' => DeliveryRoute::STATUS_PLANNED,
                'salesperson_id' => $secondarySalesperson->id,
                'field_operator_id' => null,
                'created_by_user_id' => $secondarySalesperson->user_id ?? $admin->id,
                'stops' => [
                    ['position' => 1, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $pescaTrieste->id, 'label' => $pescaTrieste->name, 'address' => $pescaTrieste->shipping_address, 'notes' => 'Entrega preparada', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 2, 'stop_type' => RouteStop::STOP_TYPE_SUGGESTED, 'target_type' => 'prospect', 'prospect_id' => $marex->id, 'label' => $marex->company_name, 'address' => $marex->address, 'notes' => 'Prospecto caliente', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 3, 'stop_type' => RouteStop::STOP_TYPE_OPPORTUNITY, 'target_type' => 'location', 'label' => 'Zona franca Trieste', 'address' => 'Zona franca 4, Trieste', 'notes' => 'Exploración secundaria', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 4, 'stop_type' => RouteStop::STOP_TYPE_SUGGESTED, 'target_type' => 'prospect', 'prospect_id' => $laguna->id, 'label' => $laguna->company_name, 'address' => $laguna->address, 'notes' => 'Parada adicional para seguimiento', 'status' => RouteStop::STATUS_PENDING],
                ],
            ],
            [
                'name' => 'Ruta Comercial Secundario - Cancelada',
                'route_template_id' => null,
                'description' => 'Ruta cancelada antes de ejecutar stops.',
                'route_date' => now()->subDays(2)->format('Y-m-d'),
                'status' => DeliveryRoute::STATUS_CANCELLED,
                'salesperson_id' => $secondarySalesperson->id,
                'field_operator_id' => null,
                'created_by_user_id' => $admin->id,
                'stops' => [
                    ['position' => 1, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $pescaTrieste->id, 'label' => $pescaTrieste->name, 'address' => $pescaTrieste->shipping_address, 'notes' => 'Ruta cancelada por logística', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 2, 'stop_type' => RouteStop::STOP_TYPE_SUGGESTED, 'target_type' => 'prospect', 'prospect_id' => $laguna->id, 'label' => $laguna->company_name, 'address' => $laguna->address, 'notes' => 'Visita aplazada', 'status' => RouteStop::STATUS_PENDING],
                ],
            ],
            [
                'name' => 'Ruta Repartidor - Mañana',
                'route_template_id' => $templates['Ruta Admin General']->id ?? null,
                'description' => 'Ruta operativa visible para el repartidor.',
                'route_date' => now()->addDay()->format('Y-m-d'),
                'status' => DeliveryRoute::STATUS_PLANNED,
                'salesperson_id' => null,
                'field_operator_id' => $fieldOperator->id,
                'created_by_user_id' => $admin->id,
                'stops' => [
                    ['position' => 1, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $clienteOperativo->id, 'label' => $clienteOperativo->name, 'address' => $clienteOperativo->shipping_address, 'notes' => 'Autoventa operativa', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 2, 'stop_type' => RouteStop::STOP_TYPE_SUGGESTED, 'target_type' => 'customer', 'customer_id' => $mercatiTirreno->id, 'label' => $mercatiTirreno->name, 'address' => $mercatiTirreno->shipping_address, 'notes' => 'Entrega adicional', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 3, 'stop_type' => RouteStop::STOP_TYPE_OPPORTUNITY, 'target_type' => 'location', 'label' => 'Mercado de barrio', 'address' => 'Parada mercado 3', 'notes' => 'Posible punto de autoventa', 'status' => RouteStop::STATUS_PENDING],
                    ['position' => 4, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $pescaTrieste->id, 'label' => $pescaTrieste->name, 'address' => $pescaTrieste->shipping_address, 'notes' => 'Cliente adicional del repartidor', 'status' => RouteStop::STATUS_PENDING],
                ],
            ],
            [
                'name' => 'Ruta Repartidor - En curso',
                'route_template_id' => null,
                'description' => 'Ruta operativa ya arrancada.',
                'route_date' => now()->format('Y-m-d'),
                'status' => DeliveryRoute::STATUS_IN_PROGRESS,
                'salesperson_id' => null,
                'field_operator_id' => $fieldOperator->id,
                'created_by_user_id' => $admin->id,
                'stops' => [
                    ['position' => 1, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $clienteOperativo->id, 'label' => $clienteOperativo->name, 'address' => $clienteOperativo->shipping_address, 'notes' => 'Entrega en progreso', 'status' => RouteStop::STATUS_COMPLETED, 'result_type' => RouteStop::RESULT_TYPE_AUTOVENTA, 'result_notes' => 'Venta directa realizada', 'completed_at' => now()->subHours(2)],
                    ['position' => 2, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $mercatiTirreno->id, 'label' => $mercatiTirreno->name, 'address' => $mercatiTirreno->shipping_address, 'notes' => 'Cliente sin contacto', 'status' => RouteStop::STATUS_SKIPPED, 'result_type' => RouteStop::RESULT_TYPE_NO_CONTACT, 'result_notes' => 'Almacén cerrado', 'completed_at' => now()->subHour()],
                    ['position' => 3, 'stop_type' => RouteStop::STOP_TYPE_SUGGESTED, 'target_type' => 'location', 'label' => 'Mercado de barrio', 'address' => 'Parada mercado 3', 'notes' => 'Pendiente de evaluar', 'status' => RouteStop::STATUS_PENDING],
                ],
            ],
            [
                'name' => 'Ruta Admin Global - Completa',
                'route_template_id' => $templates['Ruta Admin General']->id ?? null,
                'description' => 'Ruta global creada por admin con visión completa.',
                'route_date' => now()->subDays(6)->format('Y-m-d'),
                'status' => DeliveryRoute::STATUS_COMPLETED,
                'salesperson_id' => null,
                'field_operator_id' => $fieldOperator->id,
                'created_by_user_id' => $admin->id,
                'stops' => [
                    ['position' => 1, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'location', 'label' => 'Muelle Central', 'address' => 'Puerto exterior, nave logística 1', 'notes' => 'Salida de ruta', 'status' => RouteStop::STATUS_COMPLETED, 'result_type' => RouteStop::RESULT_TYPE_VISIT, 'result_notes' => 'Salida validada', 'completed_at' => now()->subDays(6)->addHour()],
                    ['position' => 2, 'stop_type' => RouteStop::STOP_TYPE_REQUIRED, 'target_type' => 'customer', 'customer_id' => $mercatiTirreno->id, 'label' => $mercatiTirreno->name, 'address' => $mercatiTirreno->shipping_address, 'notes' => 'Entrega global', 'status' => RouteStop::STATUS_COMPLETED, 'result_type' => RouteStop::RESULT_TYPE_DELIVERY, 'result_notes' => 'Recepción OK', 'completed_at' => now()->subDays(6)->addHours(3)],
                    ['position' => 3, 'stop_type' => RouteStop::STOP_TYPE_SUGGESTED, 'target_type' => 'prospect', 'prospect_id' => $blueHarbor->id, 'label' => $blueHarbor->company_name, 'address' => $blueHarbor->address, 'notes' => 'Visita de captación', 'status' => RouteStop::STATUS_COMPLETED, 'result_type' => RouteStop::RESULT_TYPE_INCIDENT, 'result_notes' => 'Se detectó incidencia de acceso al muelle', 'completed_at' => now()->subDays(6)->addHours(4)],
                ],
            ],
        ];

        foreach ($routes as $payload) {
            $route = DeliveryRoute::query()->updateOrCreate(
                ['name' => $payload['name']],
                collect($payload)->except('stops')->all()
            );

            foreach ($payload['stops'] as $stop) {
                $templateStopId = null;
                if ($route->route_template_id) {
                    $templateStopId = RouteTemplate::query()
                        ->with('stops')
                        ->find($route->route_template_id)
                        ?->stops
                        ->firstWhere('position', $stop['position'])
                        ?->id;
                }

                $routeStop = RouteStop::query()->updateOrCreate(
                    [
                        'route_id' => $route->id,
                        'position' => $stop['position'],
                    ],
                    array_merge([
                        'route_template_stop_id' => $templateStopId,
                        'result_type' => null,
                        'result_notes' => null,
                        'completed_at' => null,
                    ], $stop)
                );

                $this->linkOrderIfNeeded($route, $routeStop, $orders);
            }
        }
    }

    private function linkOrderIfNeeded(DeliveryRoute $route, RouteStop $routeStop, array $orders): void
    {
        $order = match ($route->name . '#' . $routeStop->position) {
            'Ruta Comercial Norte - Hoy#1' => $orders['ROUTE-001'],
            'Ruta Comercial Norte - En curso#1' => $orders['ROUTE-002'],
            'Ruta Comercial Secundario - Hoy#1' => $orders['ROUTE-003'],
            'Ruta Repartidor - En curso#1' => $orders['ROUTE-004'],
            default => null,
        };

        if (! $order instanceof Order) {
            return;
        }

        $order->update([
            'route_id' => $route->id,
            'route_stop_id' => $routeStop->id,
        ]);
    }
}
