<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantDevMigrate extends Command
{
    protected $signature = 'tenants:dev-migrate {--fresh} {--seed}';

    protected $description = 'Ejecuta las migraciones de tenant (companies) sobre la BD por defecto. Para desarrollo con Sail cuando no hay tenants registrados o se usa la misma BD.';

    public function handle(): int
    {
        $defaultConnection = config('database.default');
        $database = config("database.connections.{$defaultConnection}.database") ?? env('DB_DATABASE', '');

        if (empty($database)) {
            $this->error('No se pudo obtener el nombre de la base de datos. Revisa DB_DATABASE en .env o la conexión por defecto.');

            return Command::FAILURE;
        }

        $this->info("⏳ Configurando conexión tenant con base de datos: {$database}");

        config(['database.connections.tenant.database' => $database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        $params = [
            '--path' => 'database/migrations/companies',
            '--database' => 'tenant',
            '--force' => true,
        ];

        if ($this->option('fresh')) {
            Artisan::call('migrate:fresh', $params);
        } else {
            Artisan::call('migrate', $params);
        }

        $this->line(Artisan::output());

        if ($this->option('seed')) {
            $this->info('Sembrando tenant...');
            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true,
            ]);
            $this->line(Artisan::output());
        }

        $this->info('✅ Migraciones de tenant (dev) completadas.');

        return Command::SUCCESS;
    }
}
