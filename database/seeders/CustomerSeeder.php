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

        // Clientes fijos realistas (backup_reduced Brisamar: alias Cliente Nº127, Nº217, Nº130, etc.)
        $customers = [
            [
                'name' => 'Novafica S.A.',
                'alias' => 'Cliente Nº127',
                'vat_number' => 'ESA79230850',
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => "NOVAFICA S.A.\nESA79230850\nMERCAMADRID PUESTO 81\n28000 - MADRID\nESPAÑA",
                'shipping_address' => "NOVAFICA S.A.\nMERCAMADRID PUESTO 81\n28000 - MADRID\nESPAÑA",
                'salesperson_id' => $salesperson->id,
                'emails' => json_encode(['daninovafica@gmail.com']),
                'contact_info' => 'Dani Novafica',
                'country_id' => $country->id,
                'transport_id' => $transport->id,
            ],
            [
                'name' => 'Circeo Pesca S.R.L.',
                'alias' => 'Cliente Nº217',
                'vat_number' => 'IT00656120540',
                'payment_term_id' => PaymentTerm::inRandomOrder()->first()->id,
                'billing_address' => "Circeo Pesca S.R.L.\nIT00656120540\nVia Gagarin 1\n06074 San Mariano Di Corciano (Perugia)\nItalia",
                'shipping_address' => "Circeo Pesca S.R.L.\nVia Gagarin 1\n06074 San Mariano Di Corciano (Perugia)\nItalia",
                'salesperson_id' => $salesperson->id,
                'emails' => json_encode(['amministrazione@circeopesca.it']),
                'contact_info' => 'Alessandro',
                'country_id' => Country::where('name', 'like', '%Italia%')->first()?->id ?? $country->id,
                'transport_id' => $transport->id,
            ],
            [
                'name' => 'Pescnort Mar S.L.',
                'alias' => 'Cliente Nº130',
                'vat_number' => 'ESB97549570',
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => "PESCNORTMAR S.L.\nESB97549570\nC/MASET, 2-4-6\n46460 SILLA (VALENCIA)\nESPAÑA",
                'shipping_address' => "PESCNORTMAR S.L.\nC/MASET, 2-4-6\n46460 SILLA (VALENCIA)\nESPAÑA",
                'salesperson_id' => $salesperson->id,
                'emails' => json_encode(['compras@pescnort.com']),
                'contact_info' => '-',
                'country_id' => $country->id,
                'transport_id' => $transport->id,
            ],
            [
                'name' => 'Congelados del Norte S.L.',
                'alias' => 'Cliente Nº 5',
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
