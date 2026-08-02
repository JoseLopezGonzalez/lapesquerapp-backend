<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ResetTenantDatabase extends Command
{
    protected $signature = 'tenant:reset-database {subdomain} {--force : Forzar sin confirmación}';

    protected $description = 'Borra todas las tablas de un tenant y las recrea desde cero con todas las migraciones';

    public function handle(): int
    {
        $subdomain = $this->argument('subdomain');

        // Buscar el tenant
        $tenant = Tenant::where('subdomain', $subdomain)->first();

        if (! $tenant) {
            $this->error("❌ No se encontró el tenant: {$subdomain}");

            return Command::FAILURE;
        }

        $this->warn("⚠️  ADVERTENCIA: Esta operación eliminará TODAS las tablas y datos del tenant '{$subdomain}'");
        $this->line("   Base de datos: {$tenant->database}");
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm('¿Estás seguro de que deseas continuar?', false)) {
                $this->info('Operación cancelada.');

                return Command::SUCCESS;
            }
        }

        // Configurar la conexión del tenant
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        config(['database.default' => 'tenant']);

        $this->info('🔄 Eliminando todas las tablas...');

        try {
            // Obtener todas las tablas
            $tables = DB::select('SHOW TABLES');
            $tableNames = array_map(function ($table) {
                return array_values((array) $table)[0];
            }, $tables);

            if (! empty($tableNames)) {
                // Desactivar verificación de claves foráneas temporalmente
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');

                // Eliminar todas las tablas
                foreach ($tableNames as $table) {
                    DB::statement("DROP TABLE IF EXISTS `{$table}`");
                    $this->line("   ✓ Eliminada tabla: {$table}");
                }

                // Reactivar verificación de claves foráneas
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            $this->info('✅ Todas las tablas eliminadas');
            $this->newLine();

        } catch (\Exception $e) {
            $this->error("❌ Error al eliminar tablas: {$e->getMessage()}");

            return Command::FAILURE;
        }

        // Ejecutar migraciones desde cero
        $this->info('🔄 Ejecutando migraciones desde cero...');
        $this->newLine();

        try {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/companies',
                '--database' => 'tenant',
                '--force' => true,
            ]);

            $output = Artisan::output();
            $this->line($output);

            $this->info('✅ Migraciones completadas');
            $this->newLine();

        } catch (\Exception $e) {
            $this->error("❌ Error al ejecutar migraciones: {$e->getMessage()}");

            return Command::FAILURE;
        }

        // Verificar tablas creadas
        $this->info('📋 Verificando tablas creadas...');
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map(function ($table) {
            return array_values((array) $table)[0];
        }, $tables);

        sort($tableNames);
        $this->info('✅ Total de tablas creadas: '.count($tableNames));
        $this->line('   Tablas: '.implode(', ', $tableNames));

        $this->newLine();
        $this->info("🎉 Base de datos del tenant '{$subdomain}' recreada exitosamente");

        return Command::SUCCESS;
    }
}
