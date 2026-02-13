<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\Salesperson;
use App\Models\Transport;
use App\Models\Incoterm;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Pedidos de desarrollo (estados pending, finished, incident).
 * Fuente: Análisis backup tenant Brisamar — buyer_reference '-', 'Cliente Nº127', REF-####; fechas hoy y próximos 3 días.
 * Depende de: Customers, PaymentTerms, Salespeople, Transports, Incoterms.
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        if (Order::count() > 0) {
            $this->command->info('OrderSeeder: Ya existen pedidos. Omitiendo creación.');
            return;
        }

        $faker = Faker::create('es_ES');

        $customers = Customer::all();
        if ($customers->isEmpty()) {
            $this->command->warn('OrderSeeder: Ejecuta antes CustomerSeeder.');
            return;
        }

        $paymentTerm = PaymentTerm::first();
        $salesperson = Salesperson::first();
        $transport = Transport::first();
        $incoterm = Incoterm::first();

        if (!$paymentTerm || !$salesperson || !$transport) {
            $this->command->warn('OrderSeeder: Faltan PaymentTerms, Salespeople o Transports.');
            return;
        }

        $today = now()->startOfDay();
        $statuses = [Order::STATUS_PENDING, Order::STATUS_FINISHED, Order::STATUS_INCIDENT];
        // Variantes buyer_reference extraídas del backup Brisamar: '-', 'Cliente NºX', 'REF-####'
        $buyerRefVariants = ['-', 'Cliente Nº127', 'Cliente Nº217', 'REF-' . $faker->numerify('####')];

        // Instancias activas: hoy y próximos 3 días
        $activeDates = [
            $today,
            $today->copy()->addDay(),
            $today->copy()->addDays(2),
            $today->copy()->addDays(3),
        ];

        foreach ($activeDates as $loadDate) {
            $entryDate = $loadDate->copy()->subDays($faker->numberBetween(0, 2));
            Order::create([
                'customer_id' => $customers->random()->id,
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => $faker->address(),
                'shipping_address' => $faker->address(),
                'transportation_notes' => $faker->optional(0.3)->sentence(),
                'production_notes' => null,
                'accounting_notes' => null,
                'salesperson_id' => $salesperson->id,
                'emails' => json_encode([$faker->companyEmail()]),
                'transport_id' => $transport->id,
                'entry_date' => $entryDate,
                'load_date' => $loadDate,
                'status' => $faker->randomElement($statuses),
                'buyer_reference' => $faker->randomElement($buyerRefVariants),
                'incoterm_id' => $incoterm?->id,
            ]);
        }

        // Histórico: últimos 7 días (misma variedad buyer_reference)
        for ($i = 0; $i < 8; $i++) {
            $loadDate = $today->copy()->subDays($i);
            $entryDate = $loadDate->copy()->subDays($faker->numberBetween(0, 2));
            Order::create([
                'customer_id' => $customers->random()->id,
                'payment_term_id' => PaymentTerm::inRandomOrder()->first()->id,
                'billing_address' => $faker->address(),
                'shipping_address' => $faker->address(),
                'salesperson_id' => Salesperson::inRandomOrder()->first()->id,
                'emails' => json_encode([$faker->companyEmail()]),
                'transport_id' => Transport::inRandomOrder()->first()->id,
                'entry_date' => $entryDate,
                'load_date' => $loadDate,
                'status' => $faker->randomElement($statuses),
                'buyer_reference' => $faker->randomElement($buyerRefVariants),
                'incoterm_id' => Incoterm::inRandomOrder()->first()?->id,
            ]);
        }
    }
}
