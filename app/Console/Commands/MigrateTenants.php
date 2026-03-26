<?php

namespace App\Console\Commands;

use App\Support\TenantSeedDataset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;

class MigrateTenants extends Command
{
    protected $signature = 'tenants:migrate {--fresh} {--seed} {--dataset=base}';

    protected $description = 'Ejecuta las migraciones en todas las bases de datos de los tenants';

    public function handle(): int
    {
        $dataset = $this->option('dataset');

        if (! TenantSeedDataset::isValid($dataset)) {
            $this->error('Dataset no valido. Usa uno de: '.implode(', ', TenantSeedDataset::values()));

            return Command::FAILURE;
        }

        $seederClass = TenantSeedDataset::seederClassFor($dataset);
        $tenants = Tenant::active()->get();

        foreach ($tenants as $tenant) {
            $this->info("⏳ Migrando tenant: {$tenant->subdomain}");

            // Configurar la base de datos del tenant
            config(['database.connections.tenant.database' => $tenant->database]);

            // Limpiar y reconectar conexión
            DB::purge('tenant');
            DB::reconnect('tenant');

            // ⚠️ Muy importante: establecer la conexión por defecto como 'tenant'
            config(['database.default' => 'tenant']);

            // Parámetros comunes
            $params = [
                '--path' => 'database/migrations/companies',
                '--database' => 'tenant',
                '--force' => true,
            ];

            // Migración
            if ($this->option('fresh')) {
                Artisan::call('migrate:fresh', $params);
            } else {
                Artisan::call('migrate', $params);
            }

            $this->line(Artisan::output());

            // Seed opcional
            if ($this->option('seed')) {
                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => $seederClass,
                    '--force' => true,
                ]);
                $this->line(Artisan::output());
            }

            $this->info("✅ Migración completada para: {$tenant->subdomain}");
        }

        $this->info('🎉 Migraciones finalizadas para todos los tenants.');
        return Command::SUCCESS;
    }
}
