<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\CaptureZone;
use App\Models\FishingGear;
use App\Models\Process;
use App\Models\Production;
use App\Models\ProductionRecord;
use App\Models\Species;
use App\Models\Tenant;
use App\Models\User;

trait BuildsProductionScenario
{
    protected function createProductionScenario(string $slug): array
    {
        $database = config('database.connections.'.config('database.default').'.database') ?? env('DB_DATABASE', 'testing');

        Tenant::create([
            'name' => 'Test Tenant Production',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User Production',
            'email' => $slug.'@test.com',
            'password' => bcrypt('password'),
            'role' => Role::Administrador->value,
        ]);

        $gear = FishingGear::create(['name' => 'Nasas '.$slug]);
        $species = Species::create([
            'name' => 'Bacalao '.$slug,
            'scientific_name' => 'Gadus '.$slug,
            'fao' => 'COD',
            'image' => 'https://example.com/species-cod.png',
            'fishing_gear_id' => $gear->id,
        ]);
        $captureZone = CaptureZone::factory()->create();

        $production = Production::create([
            'lot' => 'LOT-'.$slug,
            'date' => now()->toDateString(),
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
            'opened_at' => now(),
        ]);

        $parentProcess = Process::create(['name' => 'Corte '.$slug, 'type' => 'process']);
        $childProcess = Process::create(['name' => 'Envasado '.$slug, 'type' => 'final']);

        $parentRecord = ProductionRecord::create([
            'production_id' => $production->id,
            'process_id' => $parentProcess->id,
            'started_at' => now()->subHour(),
        ]);

        $childRecord = ProductionRecord::create([
            'production_id' => $production->id,
            'parent_record_id' => $parentRecord->id,
            'process_id' => $childProcess->id,
            'started_at' => now(),
        ]);

        return compact(
            'slug',
            'user',
            'species',
            'captureZone',
            'production',
            'parentProcess',
            'childProcess',
            'parentRecord',
            'childRecord',
        );
    }
}
