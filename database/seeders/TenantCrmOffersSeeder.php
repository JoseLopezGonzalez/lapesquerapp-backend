<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\OfferLine;
use App\Models\Order;
use App\Models\Prospect;
use Database\Seeders\Concerns\SeedsTenantCrmData;
use Illuminate\Database\Seeder;

class TenantCrmOffersSeeder extends Seeder
{
    use SeedsTenantCrmData;

    public function run(): void
    {
        $primarySalesperson = $this->crmPrimarySalesperson();
        $secondarySalesperson = $this->crmSecondarySalesperson();
        $paymentTerm = $this->crmPaymentTerm();
        $incoterm = $this->crmIncoterm();
        $tax = $this->crmTax();
        $products = $this->crmProductPool(3)->values();

        $convertedCustomer = $this->crmCustomerByName('Rete Mare Milano');
        $order = null;

        if ($convertedCustomer) {
            $order = Order::query()->updateOrCreate(
                ['buyer_reference' => 'CRM-OFFER-ORDER-001'],
                [
                    'customer_id' => $convertedCustomer->id,
                    'payment_term_id' => $paymentTerm->id,
                    'billing_address' => $convertedCustomer->billing_address,
                    'shipping_address' => $convertedCustomer->shipping_address,
                    'salesperson_id' => $convertedCustomer->salesperson_id,
                    'created_by_user_id' => $primarySalesperson->user_id,
                    'emails' => $convertedCustomer->emails,
                    'transport_id' => $convertedCustomer->transport_id,
                    'entry_date' => now()->subDays(2)->format('Y-m-d'),
                    'load_date' => now()->addDays(3)->format('Y-m-d'),
                    'status' => Order::STATUS_PENDING,
                    'order_type' => Order::ORDER_TYPE_STANDARD,
                    'incoterm_id' => $incoterm->id,
                ]
            );
        }

        $offers = [
            [
                'notes' => '[CRM DEV] Draft BluFresco',
                'prospect_id' => Prospect::query()->where('company_name', 'BluFresco Roma')->value('id'),
                'customer_id' => null,
                'salesperson_id' => $primarySalesperson->id,
                'status' => Offer::STATUS_DRAFT,
                'send_channel' => null,
                'sent_at' => null,
                'valid_until' => now()->addDays(14)->format('Y-m-d'),
                'incoterm_id' => $incoterm->id,
                'payment_term_id' => $paymentTerm->id,
                'currency' => 'EUR',
                'accepted_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'order_id' => null,
            ],
            [
                'notes' => '[CRM DEV] Draft Porto Fino',
                'prospect_id' => Prospect::query()->where('company_name', 'Porto Fino Foods')->value('id'),
                'customer_id' => null,
                'salesperson_id' => $secondarySalesperson->id,
                'status' => Offer::STATUS_DRAFT,
                'send_channel' => null,
                'sent_at' => null,
                'valid_until' => now()->addDays(10)->format('Y-m-d'),
                'incoterm_id' => $incoterm->id,
                'payment_term_id' => $paymentTerm->id,
                'currency' => 'EUR',
                'accepted_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'order_id' => null,
            ],
            [
                'notes' => '[CRM DEV] Sent Mar e Gelo',
                'prospect_id' => Prospect::query()->where('company_name', 'Mar e Gelo Napoli')->value('id'),
                'customer_id' => null,
                'salesperson_id' => $primarySalesperson->id,
                'status' => Offer::STATUS_SENT,
                'send_channel' => Offer::SEND_CHANNEL_EMAIL,
                'sent_at' => now()->subDays(1),
                'valid_until' => now()->addDays(7)->format('Y-m-d'),
                'incoterm_id' => $incoterm->id,
                'payment_term_id' => $paymentTerm->id,
                'currency' => 'EUR',
                'accepted_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'order_id' => null,
            ],
            [
                'notes' => '[CRM DEV] Sent Marex',
                'prospect_id' => Prospect::query()->where('company_name', 'Marex Torino')->value('id'),
                'customer_id' => null,
                'salesperson_id' => $secondarySalesperson->id,
                'status' => Offer::STATUS_SENT,
                'send_channel' => Offer::SEND_CHANNEL_PDF,
                'sent_at' => now()->subDays(1),
                'valid_until' => now()->addDays(9)->format('Y-m-d'),
                'incoterm_id' => $incoterm->id,
                'payment_term_id' => $paymentTerm->id,
                'currency' => 'EUR',
                'accepted_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'order_id' => null,
            ],
            [
                'notes' => '[CRM DEV] Accepted Rete Mare',
                'prospect_id' => null,
                'customer_id' => $convertedCustomer?->id,
                'salesperson_id' => $primarySalesperson->id,
                'status' => Offer::STATUS_ACCEPTED,
                'send_channel' => Offer::SEND_CHANNEL_EMAIL,
                'sent_at' => now()->subDays(3),
                'valid_until' => now()->addDays(5)->format('Y-m-d'),
                'incoterm_id' => $incoterm->id,
                'payment_term_id' => $paymentTerm->id,
                'currency' => 'EUR',
                'accepted_at' => now()->subDays(2),
                'rejected_at' => null,
                'rejection_reason' => null,
                'order_id' => $order?->id,
            ],
            [
                'notes' => '[CRM DEV] Rejected Frio Adriatico',
                'prospect_id' => Prospect::query()->where('company_name', 'Frio Adriatico')->value('id'),
                'customer_id' => null,
                'salesperson_id' => $primarySalesperson->id,
                'status' => Offer::STATUS_REJECTED,
                'send_channel' => Offer::SEND_CHANNEL_WHATSAPP_TEXT,
                'sent_at' => now()->subDays(5),
                'valid_until' => now()->subDay()->format('Y-m-d'),
                'incoterm_id' => $incoterm->id,
                'payment_term_id' => $paymentTerm->id,
                'currency' => 'EUR',
                'accepted_at' => null,
                'rejected_at' => now()->subDays(3),
                'rejection_reason' => 'Precio fuera del presupuesto del comprador.',
                'order_id' => null,
            ],
        ];

        foreach ($offers as $payload) {
            if (! $payload['prospect_id'] && ! $payload['customer_id']) {
                continue;
            }

            $offer = Offer::query()->updateOrCreate(
                ['notes' => $payload['notes']],
                $payload
            );

            $this->syncOfferLines($offer, $products->all(), $tax->id);
        }

        $this->syncProspectStatus('Mar e Gelo Napoli', Prospect::STATUS_OFFER_SENT, now()->subHours(18));
        $this->syncProspectStatus('Marex Torino', Prospect::STATUS_OFFER_SENT, now()->subDay());
        $this->syncProspectStatus('Frio Adriatico', Prospect::STATUS_FOLLOWING, now()->subDays(5));
    }

    private function syncOfferLines(Offer $offer, array $products, int $taxId): void
    {
        $lines = match ($offer->notes) {
            '[CRM DEV] Draft BluFresco' => [
                ['description' => 'Pulpo fresco premium', 'quantity' => 120, 'unit' => 'kg', 'unit_price' => 12.45, 'boxes' => 12],
                ['description' => 'Caballa fresca mediana', 'quantity' => 85, 'unit' => 'kg', 'unit_price' => 4.90, 'boxes' => 8],
            ],
            '[CRM DEV] Draft Porto Fino' => [
                ['description' => 'Merluza fresca selección', 'quantity' => 60, 'unit' => 'kg', 'unit_price' => 9.80, 'boxes' => 6],
            ],
            '[CRM DEV] Sent Mar e Gelo' => [
                ['description' => 'Pulpo eviscerado T5', 'quantity' => 140, 'unit' => 'kg', 'unit_price' => 13.20, 'boxes' => 14],
                ['description' => 'Caballa congelada grande', 'quantity' => 90, 'unit' => 'kg', 'unit_price' => 5.15, 'boxes' => 9],
            ],
            '[CRM DEV] Sent Marex' => [
                ['description' => 'Calamar fresco horeca', 'quantity' => 70, 'unit' => 'kg', 'unit_price' => 10.40, 'boxes' => 7],
            ],
            '[CRM DEV] Accepted Rete Mare' => [
                ['description' => 'Pulpo fresco +2kg', 'quantity' => 150, 'unit' => 'kg', 'unit_price' => 14.10, 'boxes' => 15],
                ['description' => 'Sepia fresca premium', 'quantity' => 45, 'unit' => 'kg', 'unit_price' => 11.75, 'boxes' => 5],
                ['description' => 'Caballa congelada mediana', 'quantity' => 100, 'unit' => 'kg', 'unit_price' => 5.05, 'boxes' => 10],
            ],
            '[CRM DEV] Rejected Frio Adriatico' => [
                ['description' => 'Caballa fresca grande', 'quantity' => 110, 'unit' => 'kg', 'unit_price' => 5.60, 'boxes' => 11],
            ],
            default => [],
        };

        foreach ($lines as $index => $line) {
            $product = $products[$index % max(count($products), 1)] ?? null;
            if (! $product) {
                continue;
            }

            OfferLine::query()->updateOrCreate(
                [
                    'offer_id' => $offer->id,
                    'description' => $line['description'],
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price' => $line['unit_price'],
                    'tax_id' => $taxId,
                    'boxes' => $line['boxes'],
                    'currency' => 'EUR',
                ]
            );
        }
    }

    private function syncProspectStatus(string $companyName, string $status, \DateTimeInterface $lastOfferAt): void
    {
        $prospect = Prospect::query()->where('company_name', $companyName)->first();

        if (! $prospect) {
            return;
        }

        $prospect->update([
            'status' => $status,
            'last_offer_at' => $lastOfferAt,
        ]);
    }
}
