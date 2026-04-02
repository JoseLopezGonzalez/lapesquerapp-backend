<?php

namespace Database\Seeders;

use App\Models\Production;
use App\Models\ProductionCost;
use App\Models\ProductionInput;
use App\Models\ProductionOutput;
use App\Models\ProductionOutputConsumption;
use App\Models\ProductionOutputSource;
use App\Models\ProductionRecord;
use Database\Seeders\Concerns\SeedsTenantProductionData;
use Illuminate\Database\Seeder;

class TenantProductionAdvancedSeeder extends Seeder
{
    use SeedsTenantProductionData;

    public function run(): void
    {
        $species = $this->productionSpecies();
        $captureZone = $this->productionCaptureZone();
        $products = $this->productionProductPool(4);
        $boxes = $this->productionAvailableBoxes(6);
        $processes = $this->productionProcesses();

        if ($products->count() < 4 || $boxes->count() < 4) {
            $this->command?->warn('TenantProductionAdvancedSeeder: faltan productos o cajas disponibles para cerrar el dataset de producción.');
            return;
        }

        $productions = [
            [
                'lot' => 'PROD-DEV-001',
                'date' => now()->subDays(1)->format('Y-m-d'),
                'species_id' => $species->id,
                'capture_zone_id' => $captureZone->id,
                'notes' => 'Lote abierto con transformación en dos etapas.',
                'diagram_data' => ['seed' => 'production_dev'],
                'opened_at' => now()->subDays(1)->startOfDay()->addHours(6),
                'closed_at' => null,
            ],
            [
                'lot' => 'PROD-DEV-002',
                'date' => now()->subDays(4)->format('Y-m-d'),
                'species_id' => $species->id,
                'capture_zone_id' => $captureZone->id,
                'notes' => 'Lote histórico ya cerrado.',
                'diagram_data' => ['seed' => 'production_dev'],
                'opened_at' => now()->subDays(4)->startOfDay()->addHours(5),
                'closed_at' => now()->subDays(3)->startOfDay()->addHours(14),
            ],
        ];

        foreach ($productions as $payload) {
            Production::query()->updateOrCreate(['lot' => $payload['lot']], $payload);
        }

        $openProduction = Production::query()->where('lot', 'PROD-DEV-001')->firstOrFail();
        $closedProduction = Production::query()->where('lot', 'PROD-DEV-002')->firstOrFail();
        $closedProductionClosedAt = $closedProduction->closed_at;

        // El modelo impide arrancar procesos en lotes cerrados.
        // Para poder sembrar un histórico coherente, abrimos temporalmente el lote, creamos sus records y lo cerramos al final.
        if ($closedProduction->closed_at !== null) {
            $closedProduction->forceFill(['closed_at' => null])->saveQuietly();
        }

        $openRoot = ProductionRecord::query()->updateOrCreate(
            ['production_id' => $openProduction->id, 'parent_record_id' => null, 'process_id' => $processes['starting']->id],
            [
                'started_at' => now()->subHours(10),
                'finished_at' => now()->subHours(8),
                'notes' => 'Recepción y clasificación inicial',
            ]
        );
        $openChild = ProductionRecord::query()->updateOrCreate(
            ['production_id' => $openProduction->id, 'parent_record_id' => $openRoot->id, 'process_id' => $processes['process']->id],
            [
                'started_at' => now()->subHours(7),
                'finished_at' => null,
                'notes' => 'Proceso intermedio activo',
            ]
        );

        $closedRoot = ProductionRecord::query()->updateOrCreate(
            ['production_id' => $closedProduction->id, 'parent_record_id' => null, 'process_id' => $processes['starting']->id],
            [
                'started_at' => now()->subDays(4)->addHours(7),
                'finished_at' => now()->subDays(4)->addHours(9),
                'notes' => 'Recepción histórica',
            ]
        );
        $closedChild = ProductionRecord::query()->updateOrCreate(
            ['production_id' => $closedProduction->id, 'parent_record_id' => $closedRoot->id, 'process_id' => $processes['final']->id],
            [
                'started_at' => now()->subDays(4)->addHours(10),
                'finished_at' => now()->subDays(4)->addHours(13),
                'notes' => 'Envasado final histórico',
            ]
        );

        $openInputA = ProductionInput::query()->firstOrCreate([
            'production_record_id' => $openRoot->id,
            'box_id' => $boxes[0]->id,
        ]);
        $openInputB = ProductionInput::query()->firstOrCreate([
            'production_record_id' => $openRoot->id,
            'box_id' => $boxes[1]->id,
        ]);
        $closedInputA = ProductionInput::query()->firstOrCreate([
            'production_record_id' => $closedRoot->id,
            'box_id' => $boxes[2]->id,
        ]);
        $closedInputB = ProductionInput::query()->firstOrCreate([
            'production_record_id' => $closedRoot->id,
            'box_id' => $boxes[3]->id,
        ]);

        $openParentOutput = ProductionOutput::query()->updateOrCreate(
            ['production_record_id' => $openRoot->id, 'lot_id' => 'PROD-DEV-001-INT'],
            [
                'product_id' => $products[0]->id,
                'boxes' => 8,
                'weight_kg' => 84,
            ]
        );
        $closedParentOutput = ProductionOutput::query()->updateOrCreate(
            ['production_record_id' => $closedRoot->id, 'lot_id' => 'PROD-DEV-002-INT'],
            [
                'product_id' => $products[1]->id,
                'boxes' => 6,
                'weight_kg' => 61,
            ]
        );

        $openConsumption = ProductionOutputConsumption::query()->updateOrCreate(
            ['production_record_id' => $openChild->id, 'production_output_id' => $openParentOutput->id],
            [
                'consumed_weight_kg' => 44,
                'consumed_boxes' => 4,
                'notes' => 'Consumo parcial desde output padre',
            ]
        );
        $closedConsumption = ProductionOutputConsumption::query()->updateOrCreate(
            ['production_record_id' => $closedChild->id, 'production_output_id' => $closedParentOutput->id],
            [
                'consumed_weight_kg' => 61,
                'consumed_boxes' => 6,
                'notes' => 'Consumo completo del lote histórico',
            ]
        );

        $openFinalOutput = ProductionOutput::query()->updateOrCreate(
            ['production_record_id' => $openChild->id, 'lot_id' => 'PROD-DEV-001-FIN'],
            [
                'product_id' => $products[2]->id,
                'boxes' => 4,
                'weight_kg' => 40,
            ]
        );
        $closedFinalOutput = ProductionOutput::query()->updateOrCreate(
            ['production_record_id' => $closedChild->id, 'lot_id' => 'PROD-DEV-002-FIN'],
            [
                'product_id' => $products[3]->id,
                'boxes' => 5,
                'weight_kg' => 52,
            ]
        );

        $this->syncSourcesForOpenOutput($openFinalOutput, $openInputA, $openInputB, $openConsumption);
        $this->syncSourcesForClosedOutput($closedFinalOutput, $closedInputA, $closedInputB, $closedConsumption);

        $this->seedProductionCosts($openProduction, $openRoot, $openChild, $closedProduction, $closedRoot, $closedChild);

        if ($closedProductionClosedAt !== null) {
            $closedProduction->forceFill(['closed_at' => $closedProductionClosedAt])->saveQuietly();
        }
    }

    private function syncSourcesForOpenOutput(ProductionOutput $output, ProductionInput $inputA, ProductionInput $inputB, ProductionOutputConsumption $consumption): void
    {
        $this->syncStockProductSources($output, collect([$inputA, $inputB]));
        ProductionOutputSource::query()->updateOrCreate(
            ['production_output_id' => $output->id, 'source_type' => ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT, 'production_output_consumption_id' => $consumption->id],
            [
                'product_id' => null,
                'contributed_weight_kg' => (float) $consumption->consumed_weight_kg,
                'contributed_boxes' => (int) $consumption->consumed_boxes,
                'contribution_percentage' => null,
            ]
        );
    }

    private function syncSourcesForClosedOutput(ProductionOutput $output, ProductionInput $inputA, ProductionInput $inputB, ProductionOutputConsumption $consumption): void
    {
        $this->syncStockProductSources($output, collect([$inputA, $inputB]));
        ProductionOutputSource::query()->updateOrCreate(
            ['production_output_id' => $output->id, 'source_type' => ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT, 'production_output_consumption_id' => $consumption->id],
            [
                'product_id' => null,
                'contributed_weight_kg' => (float) $consumption->consumed_weight_kg,
                'contributed_boxes' => (int) $consumption->consumed_boxes,
                'contribution_percentage' => null,
            ]
        );
    }

    private function syncStockProductSources(ProductionOutput $output, \Illuminate\Support\Collection $inputs): void
    {
        $inputs
            ->filter(fn (ProductionInput $input) => $input->box && $input->box->article_id)
            ->groupBy(fn (ProductionInput $input) => (int) $input->box->article_id)
            ->each(function ($productInputs, $productId) use ($output) {
                $totalWeight = $productInputs->sum(fn (ProductionInput $input) => (float) ($input->box->net_weight ?? 0));

                ProductionOutputSource::query()->updateOrCreate(
                    [
                        'production_output_id' => $output->id,
                        'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
                        'product_id' => (int) $productId,
                    ],
                    [
                        'production_output_consumption_id' => null,
                        'contributed_weight_kg' => $totalWeight,
                        'contributed_boxes' => 0,
                        'contribution_percentage' => null,
                    ]
                );
            });
    }

    private function seedProductionCosts(
        Production $openProduction,
        ProductionRecord $openRoot,
        ProductionRecord $openChild,
        Production $closedProduction,
        ProductionRecord $closedRoot,
        ProductionRecord $closedChild
    ): void {
        $productionCatalog = \App\Models\CostCatalog::query()->where('name', 'Coste línea fileteado')->firstOrFail();
        $laborCatalog = \App\Models\CostCatalog::query()->where('name', 'Mano de obra turno mañana')->firstOrFail();
        $packagingCatalog = \App\Models\CostCatalog::query()->where('name', 'Material de envasado')->firstOrFail();
        $operationalCatalog = \App\Models\CostCatalog::query()->where('name', 'Coste operativo frío')->firstOrFail();

        ProductionCost::query()->updateOrCreate(
            ['production_record_id' => $openRoot->id, 'cost_catalog_id' => $productionCatalog->id],
            ['production_id' => null, 'total_cost' => null, 'cost_per_kg' => 1.25, 'distribution_unit' => 'per_kg', 'cost_date' => now()->toDateString()]
        );
        ProductionCost::query()->updateOrCreate(
            ['production_record_id' => $openChild->id, 'cost_catalog_id' => $packagingCatalog->id],
            ['production_id' => null, 'total_cost' => 48.50, 'cost_per_kg' => null, 'distribution_unit' => 'total', 'cost_date' => now()->toDateString()]
        );
        ProductionCost::query()->updateOrCreate(
            ['production_id' => $openProduction->id, 'cost_catalog_id' => $operationalCatalog->id],
            ['production_record_id' => null, 'total_cost' => 72.00, 'cost_per_kg' => null, 'distribution_unit' => 'total', 'cost_date' => now()->toDateString()]
        );

        ProductionCost::query()->updateOrCreate(
            ['production_record_id' => $closedRoot->id, 'cost_catalog_id' => $productionCatalog->id],
            ['production_id' => null, 'total_cost' => null, 'cost_per_kg' => 0.95, 'distribution_unit' => 'per_kg', 'cost_date' => now()->subDays(4)->toDateString()]
        );
        ProductionCost::query()->updateOrCreate(
            ['production_record_id' => $closedChild->id, 'cost_catalog_id' => $laborCatalog->id],
            ['production_id' => null, 'total_cost' => 90.00, 'cost_per_kg' => null, 'distribution_unit' => 'total', 'cost_date' => now()->subDays(4)->toDateString()]
        );
        ProductionCost::query()->updateOrCreate(
            ['production_id' => $closedProduction->id, 'cost_catalog_id' => $operationalCatalog->id],
            ['production_record_id' => null, 'total_cost' => 55.00, 'cost_per_kg' => null, 'distribution_unit' => 'total', 'cost_date' => now()->subDays(4)->toDateString()]
        );
    }
}
