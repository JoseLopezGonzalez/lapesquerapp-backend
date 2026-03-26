<?php

namespace Database\Seeders;

use App\Models\RouteStop;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStop;
use Database\Seeders\Concerns\SeedsTenantRoutesData;
use Illuminate\Database\Seeder;

class TenantRouteTemplatesSeeder extends Seeder
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

        $blueHarbor = $this->routesProspect('BlueHarbor Export', $primarySalesperson->id);
        $marex = $this->routesProspect('Marex Torino', $secondarySalesperson->id);
        $blufresco = $this->routesProspect('BluFresco Roma', $primarySalesperson->id);

        $templates = [
            [
                'name' => 'Ruta Admin General',
                'description' => 'Plantilla base del admin para reparto mixto.',
                'salesperson_id' => null,
                'field_operator_id' => $fieldOperator->id,
                'created_by_user_id' => $admin->id,
                'is_active' => true,
                'stops' => [
                    [
                        'position' => 1,
                        'stop_type' => RouteStop::STOP_TYPE_REQUIRED,
                        'target_type' => 'location',
                        'label' => 'Muelle Central',
                        'address' => 'Puerto exterior, nave logística 1',
                        'notes' => 'Carga inicial de la ruta',
                    ],
                    [
                        'position' => 2,
                        'stop_type' => RouteStop::STOP_TYPE_SUGGESTED,
                        'target_type' => 'customer',
                        'customer_id' => $mercatiTirreno->id,
                        'label' => $mercatiTirreno->name,
                        'address' => $mercatiTirreno->shipping_address,
                        'notes' => 'Entrega temprana para cliente operativo',
                    ],
                    [
                        'position' => 3,
                        'stop_type' => RouteStop::STOP_TYPE_OPPORTUNITY,
                        'target_type' => 'prospect',
                        'prospect_id' => $blueHarbor->id,
                        'label' => $blueHarbor->company_name,
                        'address' => $blueHarbor->address,
                        'notes' => 'Visita comercial de oportunidad',
                    ],
                ],
            ],
            [
                'name' => 'Ruta Comercial Norte',
                'description' => 'Cartera principal para clientes del comercial principal.',
                'salesperson_id' => $primarySalesperson->id,
                'field_operator_id' => null,
                'created_by_user_id' => $primarySalesperson->user_id ?? $admin->id,
                'is_active' => true,
                'stops' => [
                    [
                        'position' => 1,
                        'stop_type' => RouteStop::STOP_TYPE_REQUIRED,
                        'target_type' => 'customer',
                        'customer_id' => $mercatiTirreno->id,
                        'label' => $mercatiTirreno->name,
                        'address' => $mercatiTirreno->shipping_address,
                        'notes' => 'Cuenta estratégica',
                    ],
                    [
                        'position' => 2,
                        'stop_type' => RouteStop::STOP_TYPE_SUGGESTED,
                        'target_type' => 'prospect',
                        'prospect_id' => $blufresco->id,
                        'label' => $blufresco->company_name,
                        'address' => $blufresco->address,
                        'notes' => 'Seguimiento comercial de nueva cuenta',
                    ],
                    [
                        'position' => 3,
                        'stop_type' => RouteStop::STOP_TYPE_OPPORTUNITY,
                        'target_type' => 'location',
                        'label' => 'Mercado auxiliar Norte',
                        'address' => 'Puesto 12, mercado auxiliar Norte',
                        'notes' => 'Parada libre para detección de demanda',
                    ],
                ],
            ],
            [
                'name' => 'Ruta Comercial Sur',
                'description' => 'Plantilla adicional del comercial principal para costa sur.',
                'salesperson_id' => $primarySalesperson->id,
                'field_operator_id' => null,
                'created_by_user_id' => $primarySalesperson->user_id ?? $admin->id,
                'is_active' => true,
                'stops' => [
                    [
                        'position' => 1,
                        'stop_type' => RouteStop::STOP_TYPE_REQUIRED,
                        'target_type' => 'customer',
                        'customer_id' => $reteMare->id,
                        'label' => $reteMare->name,
                        'address' => $reteMare->shipping_address,
                        'notes' => 'Cliente convertido desde CRM',
                    ],
                    [
                        'position' => 2,
                        'stop_type' => RouteStop::STOP_TYPE_SUGGESTED,
                        'target_type' => 'prospect',
                        'prospect_id' => $blueHarbor->id,
                        'label' => $blueHarbor->company_name,
                        'address' => $blueHarbor->address,
                        'notes' => 'Prospecto frío para revisar',
                    ],
                ],
            ],
            [
                'name' => 'Ruta Comercial Secundario',
                'description' => 'Cartera del segundo comercial.',
                'salesperson_id' => $secondarySalesperson->id,
                'field_operator_id' => null,
                'created_by_user_id' => $secondarySalesperson->user_id ?? $admin->id,
                'is_active' => true,
                'stops' => [
                    [
                        'position' => 1,
                        'stop_type' => RouteStop::STOP_TYPE_REQUIRED,
                        'target_type' => 'customer',
                        'customer_id' => $pescaTrieste->id,
                        'label' => $pescaTrieste->name,
                        'address' => $pescaTrieste->shipping_address,
                        'notes' => 'Cliente asignado a comercial secundario',
                    ],
                    [
                        'position' => 2,
                        'stop_type' => RouteStop::STOP_TYPE_SUGGESTED,
                        'target_type' => 'prospect',
                        'prospect_id' => $marex->id,
                        'label' => $marex->company_name,
                        'address' => $marex->address,
                        'notes' => 'Prospecto con oferta enviada',
                    ],
                    [
                        'position' => 3,
                        'stop_type' => RouteStop::STOP_TYPE_OPPORTUNITY,
                        'target_type' => 'location',
                        'label' => 'Zona franca Trieste',
                        'address' => 'Zona franca 4, Trieste',
                        'notes' => 'Exploración de nuevos puntos',
                    ],
                ],
            ],
        ];

        foreach ($templates as $payload) {
            $template = RouteTemplate::query()->updateOrCreate(
                ['name' => $payload['name']],
                collect($payload)->except('stops')->all()
            );

            foreach ($payload['stops'] as $stop) {
                RouteTemplateStop::query()->updateOrCreate(
                    [
                        'route_template_id' => $template->id,
                        'position' => $stop['position'],
                    ],
                    $stop
                );
            }
        }
    }
}
