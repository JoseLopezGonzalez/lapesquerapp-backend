<?php

namespace App\Console\Commands;

use App\Models\CaptureZone;
use App\Models\FishingGear;
use App\Models\Incoterm;
use App\Models\Species;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CopyTenantEntities extends Command
{
    protected $signature = 'tenants:copy-entities 
                            {source : Subdomain del tenant origen (ej: brisamar)}
                            {destination : Subdomain del tenant destino (ej: pymcolorao)}
                            {--replace : Borra primero las entidades en el destino antes de copiar}';

    protected $description = 'Copia entidades (Incoterms, Zonas de captura, Artes de pesca, Especies) de un tenant a otro';

    public function handle(): int
    {
        $sourceSubdomain = $this->argument('source');
        $destinationSubdomain = $this->argument('destination');

        // Obtener tenants
        $sourceTenant = Tenant::where('subdomain', $sourceSubdomain)->first();
        $destinationTenant = Tenant::where('subdomain', $destinationSubdomain)->first();

        if (! $sourceTenant) {
            $this->error("❌ Tenant origen '{$sourceSubdomain}' no encontrado.");

            return Command::FAILURE;
        }

        if (! $destinationTenant) {
            $this->error("❌ Tenant destino '{$destinationSubdomain}' no encontrado.");

            return Command::FAILURE;
        }

        $this->info("📋 Copiando entidades de '{$sourceSubdomain}' a '{$destinationSubdomain}'...");

        // Configurar conexión origen
        config(['database.connections.tenant.database' => $sourceTenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Leer datos del tenant origen
        $this->info('📖 Leyendo datos del tenant origen...');
        $incoterms = Incoterm::all();
        $captureZones = CaptureZone::all();
        $fishingGears = FishingGear::all();
        $species = Species::all();

        $this->info("   - Incoterms: {$incoterms->count()}");
        $this->info("   - Zonas de captura: {$captureZones->count()}");
        $this->info("   - Artes de pesca: {$fishingGears->count()}");
        $this->info("   - Especies: {$species->count()}");

        // Configurar conexión destino
        config(['database.connections.tenant.database' => $destinationTenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Si se especifica --replace, borrar primero las entidades en el destino
        if ($this->option('replace')) {
            $this->warn("\n⚠️  Borrando entidades existentes en el destino...");

            // IMPORTANTE: Borrar en orden inverso de dependencias
            // Primero especies (dependen de artes de pesca)
            $deletedSpecies = Species::count();
            Species::query()->delete();
            $this->info("   🗑️  Borradas {$deletedSpecies} especies");

            // Luego artes de pesca (pueden tener especies relacionadas, pero ya se borraron)
            $deletedGears = FishingGear::count();
            FishingGear::query()->delete();
            $this->info("   🗑️  Borrados {$deletedGears} artes de pesca");

            // Zonas de captura
            $deletedZones = CaptureZone::count();
            CaptureZone::query()->delete();
            $this->info("   🗑️  Borradas {$deletedZones} zonas de captura");
        }

        $copied = [
            'incoterms' => 0,
            'capture_zones' => 0,
            'fishing_gears' => 0,
            'species' => 0,
        ];

        // Copiar Incoterms
        $this->info("\n📦 Copiando Incoterms...");
        foreach ($incoterms as $incoterm) {
            $existing = Incoterm::where('code', $incoterm->code)->first();
            if (! $existing) {
                Incoterm::create([
                    'code' => $incoterm->code,
                    'description' => $incoterm->description,
                ]);
                $copied['incoterms']++;
                $this->line("   ✓ Copiado: {$incoterm->code}");
            } else {
                $this->line("   ⊗ Ya existe: {$incoterm->code}");
            }
        }

        // Copiar Zonas de Captura
        $this->info("\n🌍 Copiando Zonas de Captura...");
        foreach ($captureZones as $zone) {
            if ($this->option('replace')) {
                // Si se usa --replace, crear directamente sin verificar duplicados
                CaptureZone::create([
                    'name' => $zone->name,
                ]);
                $copied['capture_zones']++;
                $this->line("   ✓ Copiado: {$zone->name}");
            } else {
                // Verificar duplicados solo si no se usa --replace
                $existing = CaptureZone::where('name', $zone->name)->first();
                if (! $existing) {
                    CaptureZone::create([
                        'name' => $zone->name,
                    ]);
                    $copied['capture_zones']++;
                    $this->line("   ✓ Copiado: {$zone->name}");
                } else {
                    $this->line("   ⊗ Ya existe: {$zone->name}");
                }
            }
        }

        // Copiar Artes de Pesca (necesario antes de copiar especies)
        $this->info("\n🎣 Copiando Artes de Pesca...");
        $fishingGearMap = []; // Mapeo de ID origen -> ID destino
        foreach ($fishingGears as $gear) {
            if ($this->option('replace')) {
                // Si se usa --replace, crear directamente sin verificar duplicados
                $newGear = FishingGear::create([
                    'name' => $gear->name,
                ]);
                $fishingGearMap[$gear->id] = $newGear->id;
                $copied['fishing_gears']++;
                $this->line("   ✓ Copiado: {$gear->name} (ID: {$gear->id} -> {$newGear->id})");
            } else {
                // Verificar duplicados solo si no se usa --replace
                $existing = FishingGear::where('name', $gear->name)->first();
                if (! $existing) {
                    $newGear = FishingGear::create([
                        'name' => $gear->name,
                    ]);
                    $fishingGearMap[$gear->id] = $newGear->id;
                    $copied['fishing_gears']++;
                    $this->line("   ✓ Copiado: {$gear->name} (ID: {$gear->id} -> {$newGear->id})");
                } else {
                    $fishingGearMap[$gear->id] = $existing->id;
                    $this->line("   ⊗ Ya existe: {$gear->name} (ID: {$gear->id} -> {$existing->id})");
                }
            }
        }

        // Copiar Especies (después de copiar artes de pesca)
        $this->info("\n🐟 Copiando Especies...");
        foreach ($species as $specie) {
            if ($this->option('replace')) {
                // Si se usa --replace, crear directamente sin verificar duplicados
                // Verificar que el fishing_gear_id mapeado existe
                $mappedFishingGearId = $fishingGearMap[$specie->fishing_gear_id] ?? null;
                if (! $mappedFishingGearId) {
                    $this->error("   ✗ Error: No se encontró arte de pesca mapeado para especie '{$specie->name}' (fishing_gear_id: {$specie->fishing_gear_id})");

                    continue;
                }

                Species::create([
                    'name' => $specie->name,
                    'scientific_name' => $specie->scientific_name,
                    'fao' => $specie->fao,
                    'image' => $specie->image ?? '',
                    'fishing_gear_id' => $mappedFishingGearId,
                ]);
                $copied['species']++;
                $this->line("   ✓ Copiado: {$specie->name} ({$specie->fao})");
            } else {
                // Verificar duplicados solo si no se usa --replace
                $existing = Species::where('name', $specie->name)
                    ->where('fao', $specie->fao)
                    ->first();

                if (! $existing) {
                    // Verificar que el fishing_gear_id mapeado existe
                    $mappedFishingGearId = $fishingGearMap[$specie->fishing_gear_id] ?? null;
                    if (! $mappedFishingGearId) {
                        $this->error("   ✗ Error: No se encontró arte de pesca mapeado para especie '{$specie->name}' (fishing_gear_id: {$specie->fishing_gear_id})");

                        continue;
                    }

                    Species::create([
                        'name' => $specie->name,
                        'scientific_name' => $specie->scientific_name,
                        'fao' => $specie->fao,
                        'image' => $specie->image ?? '',
                        'fishing_gear_id' => $mappedFishingGearId,
                    ]);
                    $copied['species']++;
                    $this->line("   ✓ Copiado: {$specie->name} ({$specie->fao})");
                } else {
                    $this->line("   ⊗ Ya existe: {$specie->name} ({$specie->fao})");
                }
            }
        }

        // Resumen
        $this->info("\n✅ Proceso completado!");
        $this->table(
            ['Entidad', 'Copiados'],
            [
                ['Incoterms', $copied['incoterms']],
                ['Zonas de Captura', $copied['capture_zones']],
                ['Artes de Pesca', $copied['fishing_gears']],
                ['Especies', $copied['species']],
            ]
        );

        return Command::SUCCESS;
    }
}
