<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    /**
     * Feature flags per plan.
     * Format: ['flag_key' => 'description', plan => enabled]
     */
    private const FLAGS = [
        // Core modules — available on all plans
        'module.sales' => [
            'description' => 'Módulo de ventas y pedidos',
            'basic' => true, 'pro' => true, 'enterprise' => true,
        ],
        'module.inventory' => [
            'description' => 'Módulo de inventario (almacenes, palets, cajas)',
            'basic' => true, 'pro' => true, 'enterprise' => true,
        ],
        'module.raw_material' => [
            'description' => 'Módulo de recepciones de materia prima',
            'basic' => true, 'pro' => true, 'enterprise' => true,
        ],

        // Pro features
        'module.production' => [
            'description' => 'Módulo de producción (fileteado, congelado, enlatado)',
            'basic' => false, 'pro' => true, 'enterprise' => true,
        ],
        'module.cebo_dispatch' => [
            'description' => 'Módulo de despachos de cebo',
            'basic' => false, 'pro' => true, 'enterprise' => true,
        ],
        'module.labels' => [
            'description' => 'Módulo de etiquetas GS1-128',
            'basic' => false, 'pro' => true, 'enterprise' => true,
        ],
        'module.punch_events' => [
            'description' => 'Módulo de fichajes de empleados',
            'basic' => false, 'pro' => true, 'enterprise' => true,
        ],

        // Enterprise features
        'module.statistics' => [
            'description' => 'Panel de estadísticas avanzadas y reportes',
            'basic' => false, 'pro' => false, 'enterprise' => true,
        ],
        'module.supplier_liquidations' => [
            'description' => 'Módulo de liquidaciones a proveedores',
            'basic' => false, 'pro' => false, 'enterprise' => true,
        ],
        'feature.import_facilcom' => [
            'description' => 'Importación desde Facilcom',
            'basic' => false, 'pro' => false, 'enterprise' => true,
        ],
        'feature.import_a3erp' => [
            'description' => 'Importación desde A3ERP',
            'basic' => false, 'pro' => false, 'enterprise' => true,
        ],
        'feature.api_access' => [
            'description' => 'Acceso a la API REST para integraciones externas',
            'basic' => false, 'pro' => false, 'enterprise' => true,
        ],
    ];

    public function run(): void
    {
        foreach (self::FLAGS as $flagKey => $config) {
            $description = $config['description'];

            foreach (['basic', 'pro', 'enterprise'] as $plan) {
                FeatureFlag::updateOrCreate(
                    ['flag_key' => $flagKey, 'plan' => $plan],
                    ['enabled' => $config[$plan], 'description' => $description]
                );
            }
        }

        $this->command->info('FeatureFlagSeeder: ' . (count(self::FLAGS) * 3) . ' flags seeded.');
    }
}
