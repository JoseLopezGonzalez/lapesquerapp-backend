<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TenantCompanySettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = Arr::dot($this->resolvedCompanyConfig());

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => "company.{$key}"],
                ['value' => $value]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedCompanyConfig(): array
    {
        $defaults = [
            'name' => 'PesquerApp Demo S.L.',
            'cif' => 'B00000001',
            'sanitary_number' => 'ES DEV DATASET',
            'address' => [
                'street' => 'Polígono Industrial Muelle Norte, Nave 3',
                'postal_code' => '21410',
                'city' => 'Isla Cristina',
                'province' => 'Huelva',
                'country' => 'España',
            ],
            'website_url' => 'https://demo.pesquerapp.local',
            'logo_url_small' => 'https://demo.pesquerapp.local/assets/logo-small.png',
            'loading_place' => 'Isla Cristina - Huelva',
            'signature_location' => 'Isla Cristina',
            'bcc_email' => 'pedidos@pesquerapp.local',
            'contact' => [
                'email_operations' => 'operaciones@pesquerapp.local',
                'email_orders' => 'pedidos@pesquerapp.local',
                'phone_orders' => '+34 600 000 001',
                'email_admin' => 'administracion@pesquerapp.local',
                'phone_admin' => '+34 600 000 002',
                'emergency_email' => 'emergencias@pesquerapp.local',
                'incidents_email' => 'incidencias@pesquerapp.local',
                'loading_email' => 'carga@pesquerapp.local',
                'unloading_email' => 'descarga@pesquerapp.local',
            ],
            'legal' => [
                'terms_url' => '/docs/condiciones-legales.pdf',
                'privacy_policy_url' => '/docs/politica-privacidad.pdf',
            ],
            'mail' => [
                'mailer' => 'smtp',
                'host' => 'mailpit',
                'port' => '1025',
                // Mailpit (Sail) no usa TLS ni auth por defecto
                'encryption' => null,
                'username' => null,
                'password' => null,
                'from_address' => 'noreply@pesquerapp.local',
                'from_name' => 'PesquerApp Demo',
            ],
        ];

        return $this->mergeMissingValues((array) config('company', []), $defaults);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function mergeMissingValues(array $config, array $defaults): array
    {
        foreach ($defaults as $key => $defaultValue) {
            $currentValue = $config[$key] ?? null;

            if (is_array($defaultValue)) {
                $config[$key] = $this->mergeMissingValues(
                    is_array($currentValue) ? $currentValue : [],
                    $defaultValue
                );
                continue;
            }

            if ($currentValue === null || $currentValue === '') {
                $config[$key] = $defaultValue;
            }
        }

        return $config;
    }
}
