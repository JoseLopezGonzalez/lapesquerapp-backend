<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class CompareTableStructure extends Command
{
    protected $signature = 'tenant:compare-structure {tenant1} {tenant2} {table}';

    protected $description = 'Compara la estructura de una tabla específica entre dos tenants';

    public function handle(): int
    {
        $tenant1Subdomain = $this->argument('tenant1');
        $tenant2Subdomain = $this->argument('tenant2');
        $tableName = $this->argument('table');

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

        $this->info("📋 Comparando estructura de la tabla '{$tableName}':");
        $this->line("   Tenant 1: {$tenant1->name} ({$tenant1->database})");
        $this->line("   Tenant 2: {$tenant2->name} ({$tenant2->database})");
        $this->newLine();

        // Obtener estructura del tenant 1
        config(['database.connections.tenant.database' => $tenant1->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $structure1 = $this->getTableStructure($tableName);

        // Obtener estructura del tenant 2
        config(['database.connections.tenant.database' => $tenant2->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $structure2 = $this->getTableStructure($tableName);

        // Comparar
        $this->info("📊 Estructura en {$tenant1->subdomain}:");
        $this->displayStructure($structure1);
        $this->newLine();

        $this->info("📊 Estructura en {$tenant2->subdomain}:");
        $this->displayStructure($structure2);
        $this->newLine();

        // Comparar columnas
        $columns1 = array_column($structure1, 'Field');
        $columns2 = array_column($structure2, 'Field');

        $onlyIn1 = array_diff($columns1, $columns2);
        $onlyIn2 = array_diff($columns2, $columns1);
        $inBoth = array_intersect($columns1, $columns2);

        if (!empty($onlyIn1)) {
            $this->warn("⚠️  Columnas solo en {$tenant1->subdomain}:");
            foreach ($onlyIn1 as $col) {
                $this->line("   - {$col}");
            }
            $this->newLine();
        }

        if (!empty($onlyIn2)) {
            $this->error("❌ Columnas faltantes en {$tenant1->subdomain} (solo en {$tenant2->subdomain}):");
            foreach ($onlyIn2 as $col) {
                $this->line("   - {$col}");
            }
            $this->newLine();
        }

        if (empty($onlyIn1) && empty($onlyIn2)) {
            $this->info("✅ Ambos tenants tienen las mismas columnas en la tabla '{$tableName}'");
        }

        return Command::SUCCESS;
    }

    private function getTableStructure(string $tableName): array
    {
        try {
            return DB::select("DESCRIBE `{$tableName}`");
        } catch (\Exception $e) {
            $this->error("❌ Error al obtener estructura: {$e->getMessage()}");
            return [];
        }
    }

    private function displayStructure(array $structure): void
    {
        if (empty($structure)) {
            $this->warn("   ⚠️  Tabla no encontrada o vacía");
            return;
        }

        $this->table(
            ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'],
            array_map(function($col) {
                return [
                    $col->Field,
                    $col->Type,
                    $col->Null,
                    $col->Key,
                    $col->Default ?? 'NULL',
                    $col->Extra,
                ];
            }, $structure)
        );
    }
}

