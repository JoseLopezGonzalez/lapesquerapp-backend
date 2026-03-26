<?php

namespace Database\Seeders;

use App\Models\AgendaAction;
use App\Models\Customer;
use App\Models\Prospect;
use Database\Seeders\Concerns\SeedsTenantCrmData;
use Illuminate\Database\Seeder;

class TenantCrmAgendaSeeder extends Seeder
{
    use SeedsTenantCrmData;

    public function run(): void
    {
        $targets = [
            [
                'target_type' => 'prospect',
                'target_id' => Prospect::query()->where('company_name', 'BluFresco Roma')->value('id'),
                'scheduled_at' => now()->format('Y-m-d'),
                'description' => 'Enviar catálogo inicial',
                'status' => 'pending',
                'source_interaction_id' => $this->interactionId('Llamada de descubrimiento BluFresco'),
                'previous_action_id' => null,
            ],
            [
                'target_type' => 'prospect',
                'target_id' => Prospect::query()->where('company_name', 'Mar e Gelo Napoli')->value('id'),
                'scheduled_at' => now()->subDay()->format('Y-m-d'),
                'description' => 'Llamar tras recepción de oferta',
                'status' => 'pending',
                'source_interaction_id' => $this->interactionId('Oferta revisada Mar e Gelo Napoli'),
                'previous_action_id' => null,
            ],
            [
                'target_type' => 'prospect',
                'target_id' => Prospect::query()->where('company_name', 'Costa Umbra Premium')->value('id'),
                'scheduled_at' => now()->subDays(4)->format('Y-m-d'),
                'description' => 'Primera propuesta técnica',
                'status' => 'reprogrammed',
                'source_interaction_id' => $this->interactionId('Seguimiento múltiple Costa Umbra'),
                'previous_action_id' => null,
            ],
            [
                'target_type' => 'customer',
                'target_id' => Customer::query()->where('name', 'Mercati Tirreno')->value('id'),
                'scheduled_at' => now()->format('Y-m-d'),
                'description' => 'Enviar muestra comercial',
                'status' => 'pending',
                'source_interaction_id' => $this->interactionId('Reactivación Mercati Tirreno'),
                'previous_action_id' => null,
            ],
            [
                'target_type' => 'customer',
                'target_id' => Customer::query()->where('name', 'Pesca Trieste')->value('id'),
                'scheduled_at' => now()->subDays(3)->format('Y-m-d'),
                'description' => 'Primer pedido piloto',
                'status' => 'cancelled',
                'source_interaction_id' => $this->interactionId('Seguimiento Pesca Trieste'),
                'previous_action_id' => null,
            ],
            [
                'target_type' => 'customer',
                'target_id' => Customer::query()->where('name', 'Rete Mare Milano')->value('id'),
                'scheduled_at' => now()->subDays(3)->format('Y-m-d'),
                'description' => 'Confirmar recompra post-conversión',
                'status' => 'done',
                'source_interaction_id' => $this->interactionId('Upsell Rete Mare Milano'),
                'completed_interaction_id' => $this->interactionId('Upsell Rete Mare Milano'),
                'previous_action_id' => null,
            ],
        ];

        foreach ($targets as $payload) {
            if (! $payload['target_id']) {
                continue;
            }

            $match = [
                'target_type' => $payload['target_type'],
                'target_id' => $payload['target_id'],
                'description' => $payload['description'],
            ];

            AgendaAction::query()->updateOrCreate($match, $payload);
        }

        $reprogrammedSourceId = $this->agendaId('prospect', 'Costa Umbra Premium', 'Primera propuesta técnica');

        if ($reprogrammedSourceId) {
            AgendaAction::query()->updateOrCreate(
                [
                    'target_type' => 'prospect',
                    'target_id' => Prospect::query()->where('company_name', 'Costa Umbra Premium')->value('id'),
                    'description' => 'Preparar visita técnica',
                ],
                [
                    'scheduled_at' => now()->addDays(4)->format('Y-m-d'),
                    'status' => 'pending',
                    'source_interaction_id' => $this->interactionId('Seguimiento múltiple Costa Umbra'),
                    'previous_action_id' => $reprogrammedSourceId,
                ]
            );
        }

        $this->syncProspectNextAction('BluFresco Roma', now()->format('Y-m-d'), 'Enviar catálogo inicial');
        $this->syncProspectNextAction('Mar e Gelo Napoli', now()->subDay()->format('Y-m-d'), 'Llamar tras recepción de oferta');
        $this->syncProspectNextAction('Costa Umbra Premium', now()->addDays(4)->format('Y-m-d'), 'Preparar visita técnica');
        $this->syncProspectNextAction('Laguna Select', null, null);
        $this->syncProspectNextAction('Porto Fino Foods', null, null);
    }

    private function syncProspectNextAction(string $companyName, ?string $date, ?string $note): void
    {
        $prospect = Prospect::query()->where('company_name', $companyName)->first();

        if (! $prospect) {
            return;
        }

        $prospect->update([
            'next_action_at' => $date,
            'next_action_note' => $date ? $note : null,
        ]);
    }

    private function interactionId(string $summary): ?int
    {
        return \App\Models\CommercialInteraction::query()->where('summary', $summary)->value('id');
    }

    private function agendaId(string $targetType, string $targetName, string $description): ?int
    {
        $targetId = $targetType === 'prospect'
            ? Prospect::query()->where('company_name', $targetName)->value('id')
            : Customer::query()->where('name', $targetName)->value('id');

        if (! $targetId) {
            return null;
        }

        return AgendaAction::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('description', $description)
            ->value('id');
    }
}
