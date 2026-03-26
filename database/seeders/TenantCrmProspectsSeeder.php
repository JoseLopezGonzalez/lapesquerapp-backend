<?php

namespace Database\Seeders;

use App\Models\CommercialInteraction;
use App\Models\Customer;
use App\Models\Prospect;
use App\Models\ProspectContact;
use Database\Seeders\Concerns\SeedsTenantCrmData;
use Illuminate\Database\Seeder;

class TenantCrmProspectsSeeder extends Seeder
{
    use SeedsTenantCrmData;

    public function run(): void
    {
        $primarySalesperson = $this->crmPrimarySalesperson();
        $secondarySalesperson = $this->crmSecondarySalesperson();
        $country = $this->crmCountry();
        $paymentTerm = $this->crmPaymentTerm();
        $transport = $this->crmTransport();
        $this->crmAdminUser();

        $convertedCustomer = Customer::query()->updateOrCreate(
            ['name' => 'Rete Mare Milano'],
            [
                'alias' => 'Cliente CRM Conversión',
                'vat_number' => 'ITCRM00001',
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => 'Via Mare 12, Milano',
                'shipping_address' => 'Via Mare 12, Milano',
                'salesperson_id' => $primarySalesperson->id,
                'emails' => 'compras@retemare.example;',
                'contact_info' => 'Marco Bianchi | Tel: +39 312000001',
                'country_id' => $country->id,
                'transport_id' => $transport->id,
            ]
        );

        $mainCustomer = Customer::query()->updateOrCreate(
            ['name' => 'Mercati Tirreno'],
            [
                'alias' => 'Cliente CRM Activo',
                'vat_number' => 'ITCRM00002',
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => 'Via Porto 8, Genova',
                'shipping_address' => 'Via Porto 8, Genova',
                'salesperson_id' => $primarySalesperson->id,
                'emails' => 'compras@mercatitirreno.example;',
                'contact_info' => 'Lucia Serra | Tel: +39 312000002',
                'country_id' => $country->id,
                'transport_id' => $transport->id,
            ]
        );

        $secondaryCustomer = Customer::query()->updateOrCreate(
            ['name' => 'Pesca Trieste'],
            [
                'alias' => 'Cliente CRM Secundario',
                'vat_number' => 'ITCRM00003',
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => 'Molo 5, Trieste',
                'shipping_address' => 'Molo 5, Trieste',
                'salesperson_id' => $secondarySalesperson->id,
                'emails' => 'compras@pescatrieste.example;',
                'contact_info' => 'Giulia Neri | Tel: +39 312000003',
                'country_id' => $country->id,
                'transport_id' => $transport->id,
            ]
        );

        $prospects = [
            [
                'company_name' => 'BluFresco Roma',
                'salesperson_id' => $primarySalesperson->id,
                'status' => Prospect::STATUS_NEW,
                'origin' => Prospect::ORIGIN_WEB,
                'address' => 'MercaRoma, puesto 18',
                'website' => 'https://blufresco-roma.example',
                'country_id' => $country->id,
                'notes' => 'Alta reciente desde formulario web.',
                'last_contact_at' => null,
            ],
            [
                'company_name' => 'Frio Adriatico',
                'salesperson_id' => $primarySalesperson->id,
                'status' => Prospect::STATUS_FOLLOWING,
                'origin' => Prospect::ORIGIN_DIRECT,
                'address' => 'Via Porto 44, Bari',
                'website' => null,
                'country_id' => $country->id,
                'notes' => 'Interés real en caballa y pulpo.',
                'last_contact_at' => now()->subDays(2),
            ],
            [
                'company_name' => 'Mar e Gelo Napoli',
                'salesperson_id' => $primarySalesperson->id,
                'status' => Prospect::STATUS_OFFER_SENT,
                'origin' => Prospect::ORIGIN_EVENT,
                'address' => 'Mercato Ittico 7, Napoli',
                'website' => 'https://maregelo.example',
                'country_id' => $country->id,
                'notes' => 'Esperando respuesta de la oferta enviada.',
                'last_contact_at' => now()->subDays(1),
                'last_offer_at' => now()->subHours(18),
            ],
            [
                'company_name' => 'Rete Mare Milano',
                'salesperson_id' => $primarySalesperson->id,
                'status' => Prospect::STATUS_CUSTOMER,
                'origin' => Prospect::ORIGIN_REFERRAL,
                'address' => 'Via Mare 12, Milano',
                'website' => 'https://retemare.example',
                'country_id' => $country->id,
                'customer_id' => $convertedCustomer->id,
                'notes' => 'Prospecto convertido tras aceptación comercial.',
                'last_contact_at' => now()->subDays(3),
                'last_offer_at' => now()->subDays(2),
            ],
            [
                'company_name' => 'Mercato Levante',
                'salesperson_id' => $primarySalesperson->id,
                'status' => Prospect::STATUS_DISCARDED,
                'origin' => Prospect::ORIGIN_ONLINE_SEARCH,
                'address' => 'Via Darsena 3, Livorno',
                'website' => null,
                'country_id' => null,
                'notes' => 'Poco encaje con el catálogo actual.',
                'lost_reason' => 'Volumen demasiado bajo para la operativa prevista.',
                'last_contact_at' => now()->subDays(9),
            ],
            [
                'company_name' => 'Costa Umbra Premium',
                'salesperson_id' => $primarySalesperson->id,
                'status' => Prospect::STATUS_FOLLOWING,
                'origin' => Prospect::ORIGIN_EMAIL,
                'address' => 'Via Lago 5, Perugia',
                'website' => 'https://costaumbra.example',
                'country_id' => $country->id,
                'notes' => 'Cuenta con varios interlocutores y seguimiento activo.',
                'last_contact_at' => now()->subDays(5),
            ],
            [
                'company_name' => 'Porto Fino Foods',
                'salesperson_id' => $secondarySalesperson->id,
                'status' => Prospect::STATUS_NEW,
                'origin' => Prospect::ORIGIN_LINKEDIN,
                'address' => 'Corso Porto 11, Venezia',
                'website' => 'https://portofinofoods.example',
                'country_id' => $country->id,
                'notes' => 'Lead reciente del segundo comercial.',
                'last_contact_at' => null,
            ],
            [
                'company_name' => 'Laguna Select',
                'salesperson_id' => $secondarySalesperson->id,
                'status' => Prospect::STATUS_FOLLOWING,
                'origin' => Prospect::ORIGIN_WHATSAPP,
                'address' => 'Canal Grande 21, Venezia',
                'website' => null,
                'country_id' => $country->id,
                'notes' => 'Seguimiento pendiente con producto congelado.',
                'last_contact_at' => now()->subDays(4),
            ],
            [
                'company_name' => 'Marex Torino',
                'salesperson_id' => $secondarySalesperson->id,
                'status' => Prospect::STATUS_OFFER_SENT,
                'origin' => Prospect::ORIGIN_AGENT,
                'address' => 'Via Mercato 88, Torino',
                'website' => 'https://marex-torino.example',
                'country_id' => null,
                'notes' => 'Oferta enviada a través de agente local.',
                'last_contact_at' => now()->subDays(2),
                'last_offer_at' => now()->subDay(),
            ],
            [
                'company_name' => 'Conservas Etna',
                'salesperson_id' => $primarySalesperson->id,
                'status' => Prospect::STATUS_FOLLOWING,
                'origin' => Prospect::ORIGIN_GOOGLE_MAPS,
                'address' => 'Via Sicilia 4, Catania',
                'website' => null,
                'country_id' => $country->id,
                'notes' => 'Sin actividad reciente, útil para dashboard.',
                'last_contact_at' => now()->subDays(11),
            ],
            [
                'company_name' => 'BlueHarbor Export',
                'salesperson_id' => $primarySalesperson->id,
                'status' => Prospect::STATUS_NEW,
                'origin' => Prospect::ORIGIN_OTHER,
                'address' => 'Via Centrale 90, Bologna',
                'website' => 'https://blueharbor.example',
                'country_id' => null,
                'notes' => 'Pendiente de primer seguimiento.',
                'last_contact_at' => null,
            ],
            [
                'company_name' => 'IceWave Sardegna',
                'salesperson_id' => $secondarySalesperson->id,
                'status' => Prospect::STATUS_FOLLOWING,
                'origin' => Prospect::ORIGIN_MARKETING_CAMPAIGN,
                'address' => 'Zona Franca 2, Cagliari',
                'website' => null,
                'country_id' => $country->id,
                'notes' => 'Prospecto frío para el dashboard.',
                'last_contact_at' => now()->subDays(14),
            ],
        ];

        foreach ($prospects as $payload) {
            Prospect::query()->updateOrCreate(
                ['company_name' => $payload['company_name']],
                $payload
            );
        }

        $contacts = [
            'BluFresco Roma' => [
                ['name' => 'Ana Ferri', 'role' => 'Compras', 'phone' => '+39 600100001', 'email' => 'ana.ferri@blufresco.example', 'is_primary' => true],
            ],
            'Frio Adriatico' => [
                ['name' => 'Matteo Russo', 'role' => 'Director comercial', 'phone' => '+39 600100002', 'email' => 'matteo.russo@frioadriatico.example', 'is_primary' => true],
                ['name' => 'Silvia Moro', 'role' => 'Backoffice', 'phone' => '+39 600100003', 'email' => 'silvia.moro@frioadriatico.example', 'is_primary' => false],
            ],
            'Mar e Gelo Napoli' => [
                ['name' => 'Gianni Costa', 'role' => 'Comprador', 'phone' => '+39 600100004', 'email' => 'gianni.costa@maregelo.example', 'is_primary' => true],
                ['name' => 'Sara Leone', 'role' => 'Administración', 'phone' => '+39 600100005', 'email' => 'sara.leone@maregelo.example', 'is_primary' => false],
            ],
            'Rete Mare Milano' => [
                ['name' => 'Marco Bianchi', 'role' => 'CEO', 'phone' => '+39 600100006', 'email' => 'marco.bianchi@retemare.example', 'is_primary' => false],
                ['name' => 'Chiara Neri', 'role' => 'Compras', 'phone' => '+39 600100007', 'email' => 'chiara.neri@retemare.example', 'is_primary' => true],
                ['name' => 'Luca Villa', 'role' => 'Logística', 'phone' => '+39 600100008', 'email' => 'luca.villa@retemare.example', 'is_primary' => false],
            ],
            'Mercato Levante' => [
                ['name' => 'Paolo Serra', 'role' => 'Gerencia', 'phone' => '+39 600100009', 'email' => 'paolo.serra@mercatolevante.example', 'is_primary' => true],
            ],
            'Costa Umbra Premium' => [
                ['name' => 'Laura Vichi', 'role' => 'Compras', 'phone' => '+39 600100010', 'email' => 'laura.vichi@costaumbra.example', 'is_primary' => true],
                ['name' => 'Davide Orsi', 'role' => 'Calidad', 'phone' => '+39 600100011', 'email' => 'davide.orsi@costaumbra.example', 'is_primary' => false],
                ['name' => 'Elena Sala', 'role' => 'Operaciones', 'phone' => '+39 600100012', 'email' => 'elena.sala@costaumbra.example', 'is_primary' => false],
            ],
            'Porto Fino Foods' => [
                ['name' => 'Irene Sarti', 'role' => 'Compras', 'phone' => '+39 600100013', 'email' => 'irene.sarti@portofinofoods.example', 'is_primary' => true],
            ],
            'Laguna Select' => [
                ['name' => 'Marta Gallo', 'role' => 'Administración', 'phone' => '+39 600100014', 'email' => 'marta.gallo@lagunaselect.example', 'is_primary' => true],
            ],
            'Marex Torino' => [
                ['name' => 'Fabio Greco', 'role' => 'Comprador', 'phone' => '+39 600100015', 'email' => 'fabio.greco@marex.example', 'is_primary' => true],
                ['name' => 'Giada Longo', 'role' => 'Dirección', 'phone' => '+39 600100016', 'email' => 'giada.longo@marex.example', 'is_primary' => false],
            ],
            'Conservas Etna' => [
                ['name' => 'Rosa Messina', 'role' => 'Compras', 'phone' => '+39 600100017', 'email' => 'rosa.messina@etna.example', 'is_primary' => true],
            ],
            'BlueHarbor Export' => [
                ['name' => 'Pietro Lodi', 'role' => 'Dirección', 'phone' => '+39 600100018', 'email' => 'pietro.lodi@blueharbor.example', 'is_primary' => true],
            ],
            'IceWave Sardegna' => [
                ['name' => 'Claudia Piras', 'role' => 'Compras', 'phone' => '+39 600100019', 'email' => 'claudia.piras@icewave.example', 'is_primary' => true],
            ],
        ];

        foreach ($contacts as $companyName => $companyContacts) {
            $prospect = Prospect::query()->where('company_name', $companyName)->first();
            if (! $prospect) {
                continue;
            }

            foreach ($companyContacts as $contact) {
                ProspectContact::query()->updateOrCreate(
                    [
                        'prospect_id' => $prospect->id,
                        'email' => $contact['email'],
                    ],
                    $contact
                );
            }
        }

        $interactions = [
            [
                'summary' => 'Llamada de descubrimiento BluFresco',
                'salesperson_id' => $primarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'BluFresco Roma')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_CALL,
                'result' => CommercialInteraction::RESULT_PENDING,
                'occurred_at' => now()->subDays(1)->setTime(10, 0),
                'next_action_note' => 'Enviar catálogo inicial',
                'next_action_at' => now()->addDay()->format('Y-m-d'),
            ],
            [
                'summary' => 'Email de seguimiento Frio Adriatico',
                'salesperson_id' => $primarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'Frio Adriatico')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_EMAIL,
                'result' => CommercialInteraction::RESULT_INTERESTED,
                'occurred_at' => now()->subDays(6)->setTime(9, 30),
            ],
            [
                'summary' => 'WhatsApp de precios Frio Adriatico',
                'salesperson_id' => $primarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'Frio Adriatico')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_WHATSAPP,
                'result' => CommercialInteraction::RESULT_PENDING,
                'occurred_at' => now()->subDays(2)->setTime(12, 0),
                'next_action_note' => 'Confirmar packing final',
                'next_action_at' => now()->addDays(2)->format('Y-m-d'),
            ],
            [
                'summary' => 'Primera visita Mar e Gelo Napoli',
                'salesperson_id' => $primarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'Mar e Gelo Napoli')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_VISIT,
                'result' => CommercialInteraction::RESULT_INTERESTED,
                'occurred_at' => now()->subDays(9)->setTime(11, 0),
            ],
            [
                'summary' => 'Oferta revisada Mar e Gelo Napoli',
                'salesperson_id' => $primarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'Mar e Gelo Napoli')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_EMAIL,
                'result' => CommercialInteraction::RESULT_PENDING,
                'occurred_at' => now()->subDays(1)->setTime(16, 0),
                'next_action_note' => 'Llamar tras recepción de oferta',
                'next_action_at' => now()->addDay()->format('Y-m-d'),
            ],
            [
                'summary' => 'Seguimiento múltiple Costa Umbra',
                'salesperson_id' => $primarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'Costa Umbra Premium')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_CALL,
                'result' => CommercialInteraction::RESULT_PENDING,
                'occurred_at' => now()->subDays(5)->setTime(13, 0),
                'next_action_note' => 'Preparar visita técnica',
                'next_action_at' => now()->addDays(4)->format('Y-m-d'),
            ],
            [
                'summary' => 'Alta Porto Fino Foods',
                'salesperson_id' => $secondarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'Porto Fino Foods')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_EMAIL,
                'result' => CommercialInteraction::RESULT_NO_RESPONSE,
                'occurred_at' => now()->subDays(3)->setTime(10, 45),
            ],
            [
                'summary' => 'Seguimiento Laguna Select',
                'salesperson_id' => $secondarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'Laguna Select')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_CALL,
                'result' => CommercialInteraction::RESULT_PENDING,
                'occurred_at' => now()->subDays(4)->setTime(17, 15),
                'next_action_note' => 'Enviar propuesta de congelado',
                'next_action_at' => now()->addDays(3)->format('Y-m-d'),
            ],
            [
                'summary' => 'Presentación Marex Torino',
                'salesperson_id' => $secondarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'Marex Torino')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_VISIT,
                'result' => CommercialInteraction::RESULT_INTERESTED,
                'occurred_at' => now()->subDays(8)->setTime(9, 0),
            ],
            [
                'summary' => 'Revisión final oferta Marex Torino',
                'salesperson_id' => $secondarySalesperson->id,
                'prospect_id' => Prospect::query()->where('company_name', 'Marex Torino')->value('id'),
                'customer_id' => null,
                'type' => CommercialInteraction::TYPE_WHATSAPP,
                'result' => CommercialInteraction::RESULT_PENDING,
                'occurred_at' => now()->subDays(2)->setTime(18, 0),
                'next_action_note' => 'Esperar aprobación de gerencia',
                'next_action_at' => now()->addDays(2)->format('Y-m-d'),
            ],
            [
                'summary' => 'Reactivación Mercati Tirreno',
                'salesperson_id' => $primarySalesperson->id,
                'prospect_id' => null,
                'customer_id' => $mainCustomer->id,
                'type' => CommercialInteraction::TYPE_CALL,
                'result' => CommercialInteraction::RESULT_PENDING,
                'occurred_at' => now()->subDays(2)->setTime(8, 45),
                'next_action_note' => 'Enviar muestra comercial',
                'next_action_at' => now()->format('Y-m-d'),
            ],
            [
                'summary' => 'Upsell Rete Mare Milano',
                'salesperson_id' => $primarySalesperson->id,
                'prospect_id' => null,
                'customer_id' => $convertedCustomer->id,
                'type' => CommercialInteraction::TYPE_EMAIL,
                'result' => CommercialInteraction::RESULT_INTERESTED,
                'occurred_at' => now()->subDays(3)->setTime(15, 20),
            ],
            [
                'summary' => 'Seguimiento Pesca Trieste',
                'salesperson_id' => $secondarySalesperson->id,
                'prospect_id' => null,
                'customer_id' => $secondaryCustomer->id,
                'type' => CommercialInteraction::TYPE_CALL,
                'result' => CommercialInteraction::RESULT_PENDING,
                'occurred_at' => now()->subDays(1)->setTime(11, 15),
                'next_action_note' => 'Confirmar pedido piloto',
                'next_action_at' => now()->addDays(1)->format('Y-m-d'),
            ],
        ];

        foreach ($interactions as $payload) {
            CommercialInteraction::query()->updateOrCreate(
                ['summary' => $payload['summary']],
                $payload
            );
        }
    }
}
