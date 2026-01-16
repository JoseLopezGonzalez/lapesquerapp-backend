<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class CompareMigrations extends Command
{
    protected $signature = 'tenant:compare-migrations {tenant1} {tenant2}';

    protected $description = 'Compara las migraciones aplicadas entre dos tenants';

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

        $this->info("📋 Comparando migraciones:");
        $this->line("   Tenant 1: {$tenant1->name} ({$tenant1->database})");
        $this->line("   Tenant 2: {$tenant2->name} ({$tenant2->database})");
        $this->newLine();

        // Obtener migraciones del tenant 1
        config(['database.connections.tenant.database' => $tenant1->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $migrations1 = $this->getMigrations();

        // Obtener migraciones del tenant 2
        config(['database.connections.tenant.database' => $tenant2->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $migrations2 = $this->getMigrations();

        // Comparar
        $onlyIn1 = array_diff($migrations1, $migrations2);
        $onlyIn2 = array_diff($migrations2, $migrations1);
        $inBoth = array_intersect($migrations1, $migrations2);

        $this->info("📊 Resultados:");
        $this->line("   {$tenant1->subdomain}: " . count($migrations1) . " migraciones");
        $this->line("   {$tenant2->subdomain}: " . count($migrations2) . " migraciones");
        $this->line("   Comunes: " . count($inBoth));
        $this->newLine();

        if (!empty($onlyIn2)) {
            $this->error("❌ Migraciones faltantes en {$tenant1->subdomain} (solo en {$tenant2->subdomain}) (" . count($onlyIn2) . "):");
            foreach ($onlyIn2 as $migration) {
                $this->line("   - {$migration}");
            }
            $this->newLine();
        }

        if (!empty($onlyIn1)) {
            $this->warn("⚠️  Migraciones solo en {$tenant1->subdomain} (" . count($onlyIn1) . "):");
            foreach ($onlyIn1 as $migration) {
                $this->line("   - {$migration}");
            }
            $this->newLine();
        }

        if (empty($onlyIn1) && empty($onlyIn2)) {
            $this->info("✅ Ambos tenants tienen las mismas migraciones aplicadas");
        }

        return Command::SUCCESS;
    }

    private function getMigrations(): array
    {
        try {
            $migrations = DB::table('migrations')->orderBy('id')->pluck('migration')->toArray();
            return $migrations;
        } catch (\Exception $e) {
            $this->error("❌ Error al obtener migraciones: {$e->getMessage()}");
            return [];
        }
    }
}

