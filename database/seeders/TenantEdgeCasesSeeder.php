<?php

namespace Database\Seeders;

use App\Models\AgendaAction;
use App\Models\CommercialInteraction;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\ExternalUser;
use App\Models\Incident;
use App\Models\Offer;
use App\Models\OfferLine;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\Prospect;
use App\Models\RouteStop;
use App\Models\Salesperson;
use Illuminate\Database\Seeder;

/**
 * Casos límite controlados para QA manual.
 * Todo queda fuera del flujo base y usa nombres claramente identificables.
 */
class TenantEdgeCasesSeeder extends Seeder
{
    public function run(): void
    {
        $salespersonId = Salesperson::query()->value('id');

        $customerWithoutAlias = Customer::query()->updateOrCreate(
            ['name' => 'Cliente Edge Alias Nulo'],
            Customer::factory()->withoutAlias()->make([
                'name' => 'Cliente Edge Alias Nulo',
                'vat_number' => 'ESEDGE0001',
                'operational_status' => 'paused',
                'salesperson_id' => $salespersonId,
                'emails' => 'cliente.edge@pesquerapp.test;',
                'contact_info' => 'Cliente edge con alias nulo',
            ])->getAttributes()
        );

        $discardedProspect = Prospect::query()->updateOrCreate(
            ['company_name' => 'Prospecto Edge Descartado'],
            Prospect::factory()->discarded()->make([
                'salesperson_id' => $salespersonId,
                'origin' => Prospect::ORIGIN_OTHER,
                'country_id' => null,
                'website' => null,
                'last_contact_at' => now()->subMonths(3),
                'next_action_at' => null,
                'next_action_note' => null,
                'lost_reason' => 'Sin capacidad logística y sin respuesta',
                'notes' => 'Caso extremo para QA manual',
                'company_name' => 'Prospecto Edge Descartado',
            ])->getAttributes()
        );

        $convertedProspect = Prospect::query()->updateOrCreate(
            ['company_name' => 'Prospecto Edge Convertido'],
            Prospect::factory()->customer()->make([
                'salesperson_id' => $salespersonId,
                'origin' => Prospect::ORIGIN_DIRECT,
                'customer_id' => $customerWithoutAlias->id,
                'last_contact_at' => now()->subDays(8),
                'next_action_at' => null,
                'next_action_note' => null,
                'lost_reason' => null,
                'company_name' => 'Prospecto Edge Convertido',
            ])->getAttributes()
        );

        $interaction = CommercialInteraction::factory()
            ->forProspect($discardedProspect)
            ->pendingFollowUp()
            ->create([
                'summary' => 'Seguimiento edge para prospecto sin respuesta',
            ]);

        $previousAgenda = AgendaAction::query()->updateOrCreate(
            [
                'target_type' => 'prospect',
                'target_id' => $discardedProspect->id,
                'description' => 'Agenda edge reprogramada',
            ],
            [
                'scheduled_at' => now()->subDay()->format('Y-m-d'),
                'status' => 'reprogrammed',
                'source_interaction_id' => $interaction->id,
                'completed_interaction_id' => null,
                'previous_action_id' => null,
            ]
        );

        AgendaAction::query()->updateOrCreate(
            [
                'target_type' => 'prospect',
                'target_id' => $discardedProspect->id,
                'description' => 'Agenda edge pendiente actual',
            ],
            [
                'scheduled_at' => now()->addDay()->format('Y-m-d'),
                'status' => 'pending',
                'source_interaction_id' => $interaction->id,
                'completed_interaction_id' => null,
                'previous_action_id' => $previousAgenda->id,
            ]
        );

        $edgeOrder = Order::query()->firstOrCreate(
            ['buyer_reference' => 'EDGE-ORDER-0001'],
            Order::factory()->incident()->withLines(2)->make([
                'customer_id' => $customerWithoutAlias->id,
                'salesperson_id' => $salespersonId,
                'buyer_reference' => 'EDGE-ORDER-0001',
            ])->getAttributes()
        );

        if ($edgeOrder->plannedProductDetails()->count() === 0) {
            OrderPlannedProductDetail::factory()
                ->count(2)
                ->for($edgeOrder, 'order')
                ->create();
        }

        $acceptedOffer = Offer::query()->updateOrCreate(
            ['notes' => 'Oferta edge aceptada'],
            Offer::factory()->accepted()->forCustomer($customerWithoutAlias)->make([
                'order_id' => $edgeOrder->id,
                'notes' => 'Oferta edge aceptada',
            ])->getAttributes()
        );

        if ($acceptedOffer->lines()->count() === 0) {
            OfferLine::factory()->count(2)->forOffer($acceptedOffer)->create();
        }

        $rejectedOffer = Offer::query()->updateOrCreate(
            ['notes' => 'Oferta edge rechazada'],
            Offer::factory()->rejected()->forProspect($discardedProspect)->make([
                'notes' => 'Oferta edge rechazada',
            ])->getAttributes()
        );

        if ($rejectedOffer->lines()->count() === 0) {
            OfferLine::factory()->count(1)->forOffer($rejectedOffer)->create();
        }

        $edgeRoute = DeliveryRoute::query()->firstOrCreate(
            ['name' => 'Ruta Edge Cancelada'],
            DeliveryRoute::factory()->cancelled()->make([
                'name' => 'Ruta Edge Cancelada',
                'salesperson_id' => $salespersonId,
            ])->getAttributes()
        );

        if ($edgeRoute->stops()->count() === 0) {
            RouteStop::factory()->forCustomer($customerWithoutAlias)->skipped()->for($edgeRoute, 'route')->create([
                'position' => 1,
                'result_notes' => 'Cliente ausente, no se pudo entregar',
            ]);

            RouteStop::factory()->forProspectModel($convertedProspect)->pending()->for($edgeRoute, 'route')->create([
                'position' => 2,
                'notes' => 'Stop pendiente tras cancelacion',
            ]);
        }

        Incident::query()->updateOrCreate(
            ['description' => 'Incidencia edge abierta'],
            [
                'order_id' => $edgeOrder->id,
                'status' => Incident::STATUS_OPEN,
                'resolution_type' => null,
                'resolution_notes' => null,
                'resolved_at' => null,
            ]
        );

        Incident::query()->updateOrCreate(
            ['description' => 'Incidencia edge resuelta'],
            [
                'order_id' => $edgeOrder->id,
                'status' => Incident::STATUS_RESOLVED,
                'resolution_type' => Incident::getValidResolutionTypes()[0] ?? null,
                'resolution_notes' => 'Resuelta durante escenario edge',
                'resolved_at' => now()->subHours(5),
            ]
        );

        ExternalUser::query()->updateOrCreate(
            ['email' => 'external.edge@pesquerapp.test'],
            [
                'name' => 'External Edge Inactivo',
                'company_name' => 'Proveedor Edge',
                'type' => ExternalUser::TYPE_MAQUILADOR,
                'is_active' => false,
                'notes' => 'Usuario externo inactivo para QA manual',
            ]
        );
    }
}
