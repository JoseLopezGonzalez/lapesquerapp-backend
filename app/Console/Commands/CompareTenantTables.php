<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class CompareTenantTables extends Command
{
    protected $signature = 'tenant:compare-tables {tenant1} {tenant2}';

    protected $description = 'Compara las tablas de dos tenants para identificar diferencias';

    public function handle(): int
    {
        $tenant1Subdomain = $this->argument('tenant1');
        $tenant2Subdomain = $this->argument('tenant2');

        // Buscar los tenants
        $tenant1 = Tenant::where('subdomain', $tenant1Subdomain)->first();
        $tenant2 = Tenant::where('subdomain', $tenant2Subdomain)->first();

        if (!$tenant1) {
            $this->error("❌ No se encontró el tenant: {$tenant1Subdomain}");
            return Command::FAILURE;
        }

        if (!$tenant2) {
            $this->error("❌ No se encontró el tenant: {$tenant2Subdomain}");
            return Command::FAILURE;
        }

        $this->info("📋 Comparando tablas:");
        $this->line("   Tenant 1: {$tenant1->name} ({$tenant1->database})");
        $this->line("   Tenant 2: {$tenant2->name} ({$tenant2->database})");
        $this->newLine();

        // Obtener tablas del tenant 1
        $this->info("🔍 Obteniendo tablas de {$tenant1->subdomain}...");
        config(['database.connections.tenant.database' => $tenant1->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $tables1 = $this->getTables();
        $this->info("   ✅ Encontradas " . count($tables1) . " tablas");

        // Obtener tablas del tenant 2
        $this->info("🔍 Obteniendo tablas de {$tenant2->subdomain}...");
        config(['database.connections.tenant.database' => $tenant2->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $tables2 = $this->getTables();
        $this->info("   ✅ Encontradas " . count($tables2) . " tablas");
        $this->newLine();

        // Comparar
        $onlyIn1 = array_diff($tables1, $tables2);
        $onlyIn2 = array_diff($tables2, $tables1);
        $inBoth = array_intersect($tables1, $tables2);

        // Mostrar resultados
        $this->info("📊 Resultados de la comparación:");
        $this->newLine();

        if (empty($onlyIn1) && empty($onlyIn2)) {
            $this->info("✅ Ambos tenants tienen las mismas tablas (" . count($inBoth) . " tablas)");
        } else {
            if (!empty($onlyIn1)) {
                $this->warn("⚠️  Tablas solo en {$tenant1->subdomain} (" . count($onlyIn1) . "):");
                foreach ($onlyIn1 as $table) {
                    $this->line("   - {$table}");
                }
                $this->newLine();
            }

            if (!empty($onlyIn2)) {
                $this->error("❌ Tablas faltantes en {$tenant1->subdomain} (solo en {$tenant2->subdomain}) (" . count($onlyIn2) . "):");
                foreach ($onlyIn2 as $table) {
                    $this->line("   - {$table}");
                }
                $this->newLine();
            }

            $this->info("✅ Tablas comunes: " . count($inBoth));
        }

        // Mostrar todas las tablas de cada tenant
        $this->newLine();
        sort($tables1);
        $this->info("📋 Todas las tablas de {$tenant1->subdomain} (" . count($tables1) . "):");
        foreach ($tables1 as $table) {
            $this->line("   - {$table}");
        }

        $this->newLine();
        sort($tables2);
        $this->info("📋 Todas las tablas de {$tenant2->subdomain} (" . count($tables2) . "):");
        foreach ($tables2 as $table) {
            $this->line("   - {$table}");
        }

        return Command::SUCCESS;
    }

    private function getTables(): array
    {
        $tables = DB::select("SHOW TABLES");
        $result = [];
        foreach ($tables as $table) {
            // La clave dinámica puede variar según el nombre de la BD
            $tableArray = (array) $table;
            $result[] = reset($tableArray);
        }
        return $result;
    }
}

