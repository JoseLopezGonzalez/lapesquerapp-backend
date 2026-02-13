<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Country;
use App\Models\PaymentTerm;
use App\Models\Salesperson;
use App\Models\Transport;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Clientes de desarrollo.
 * Fuente: Análisis esquema tenant. Depende de: Countries, PaymentTerms, Salespeople, Transports.
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $country = Country::first();
        $paymentTerm = PaymentTerm::first();
        $salesperson = Salesperson::first();
        $transport = Transport::first();

        if (!$country || !$paymentTerm || !$salesperson || !$transport) {
            $this->command->warn('CustomerSeeder: Ejecuta antes Countries, PaymentTerms, Salespeople y Transports.');
            return;
        }

        // Clientes fijos realistas (estilo backup Brisamar: ES/IT, nombres empresa)
        $customers = [
            [
                'name' => 'Congelados del Norte S.L.',
                'alias' => 'Cong Norte',
                'vat_number' => 'B12345678',
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => 'Polígono Industrial, Nave 12, 15008 A Coruña',
                'shipping_address' => 'Polígono Industrial, Nave 12, 15008 A Coruña',
                'salesperson_id' => $salesperson->id,
                'emails' => json_encode(['compras@congeladosnorte.es']),
                'contact_info' => 'Juan García, 981 123 456',
                'country_id' => $country->id,
                'transport_id' => $transport->id,
            ],
            [
                'name' => 'Pescados Frescos Mediterráneo',
                'alias' => null,
                'vat_number' => 'B87654321',
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => $faker->address(),
                'shipping_address' => $faker->address(),
                'salesperson_id' => $salesperson->id,
                'emails' => json_encode([$faker->companyEmail()]),
                'contact_info' => $faker->name() . ', ' . $faker->phoneNumber(),
                'country_id' => $country->id,
                'transport_id' => $transport->id,
            ],
            [
                'name' => 'Novafica S.A.',
                'alias' => 'Cliente Nº127',
                'vat_number' => 'ESA79230850',
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => "NOVAFICA S.A.\nMERCAMADRID PUESTO 81\n28000 - MADRID\nESPAÑA",
                'shipping_address' => "NOVAFICA S.A.\nMERCAMADRID PUESTO 81\n28000 - MADRID\nESPAÑA",
                'salesperson_id' => $salesperson->id,
                'emails' => json_encode(['daninovafica@gmail.com']),
                'contact_info' => 'Dani Novafica',
                'country_id' => $country->id,
                'transport_id' => $transport->id,
            ],
        ];

        foreach ($customers as $data) {
            Customer::firstOrCreate(
                ['vat_number' => $data['vat_number']],
                $data
            );
        }

        for ($i = 0; $i < 5; $i++) {
            Customer::firstOrCreate(
                ['vat_number' => 'B' . $faker->unique()->numerify('########')],
                [
                    'name' => $faker->company(),
                    'alias' => null,
                    'payment_term_id' => PaymentTerm::inRandomOrder()->first()->id,
                    'billing_address' => $faker->address(),
                    'shipping_address' => $faker->address(),
                    'salesperson_id' => Salesperson::inRandomOrder()->first()->id,
                    'emails' => json_encode([$faker->companyEmail()]),
                    'contact_info' => $faker->name() . ', ' . $faker->phoneNumber(),
                    'country_id' => Country::inRandomOrder()->first()->id,
                    'transport_id' => Transport::inRandomOrder()->first()->id,
                ]
            );
        }
    }
}
