<?php

namespace App\Console\Commands;

use App\Support\TenantSeedDataset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;

class SeedTenants extends Command
{
    protected $signature = 'tenants:seed {--class=} {--dataset=base}';

    protected $description = 'Ejecuta seeders en todas las bases de datos de los tenants';

    public function handle(): int
    {
        $dataset = $this->option('dataset');

        if (! TenantSeedDataset::isValid($dataset)) {
            $this->error('Dataset no valido. Usa uno de: '.implode(', ', TenantSeedDataset::values()));

            return Command::FAILURE;
        }

        $tenants = Tenant::active()->get();

        foreach ($tenants as $tenant) {
            $this->info("⏳ Ejecutando seeders en tenant: {$tenant->subdomain}");

            // Configurar la base de datos del tenant
            config(['database.connections.tenant.database' => $tenant->database]);

            // Limpiar y reconectar conexión
            DB::purge('tenant');
            DB::reconnect('tenant');

            // ⚠️ Muy importante: establecer la conexión por defecto como 'tenant'
            config(['database.default' => 'tenant']);

            // Parámetros para el seeder
            $params = [
                '--database' => 'tenant',
                '--force' => true,
            ];

            // Si se especifica una clase específica
            if ($this->option('class')) {
                $params['--class'] = $this->option('class');
            } else {
                $params['--class'] = TenantSeedDataset::seederClassFor($dataset);
            }

            // Ejecutar seeder
            Artisan::call('db:seed', $params);

            $this->line(Artisan::output());

            $this->info("✅ Seeders completados para: {$tenant->subdomain}");
        }

        $this->info('🎉 Seeders finalizados para todos los tenants.');
        return Command::SUCCESS;
    }
}
