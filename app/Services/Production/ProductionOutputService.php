<?php

namespace App\Services\Production;

use App\Models\ProductionOutput;
use App\Models\ProductionOutputSource;
use Illuminate\Support\Facades\DB;

class ProductionOutputService
{
    /**
     * Create a production output
     */
    public function create(array $data): ProductionOutput
    {
        return DB::transaction(function () use ($data) {
            $sources = $data['sources'] ?? null;
            unset($data['sources']);

            $output = ProductionOutput::create($data);

            // Crear sources si se proporcionaron
            if ($sources !== null && is_array($sources)) {
                $this->createSources($output, $sources);
            } else {
                // Si no se proporcionaron sources, calcular automáticamente de forma proporcional
                $this->createSourcesAutomatically($output);
            }

            $output->load([
                'productionRecord.production',
                'product',
                'sources.product',
                'sources.productionOutput.productionRecord',
                'sources.productionOutputConsumption.productionOutput.product',
                'sources.productionOutputConsumption.productionOutput.productionRecord.production',
            ]);

            return $output;
        });
    }

    /**
     * Crear sources manualmente
     */
    protected function createSources(ProductionOutput $output, array $sources): void
    {
        foreach ($sources as $sourceData) {
            $source = new ProductionOutputSource([
                'production_output_id' => $output->id,
                'source_type' => $sourceData['source_type'],
                'product_id' => $sourceData['product_id'] ?? null,
                'production_output_consumption_id' => $sourceData['production_output_consumption_id'] ?? null,
                'contributed_weight_kg' => $sourceData['contributed_weight_kg'] ?? null,
                'contributed_boxes' => $sourceData['contributed_boxes'] ?? 0,
            ]);
            if (array_key_exists('contribution_percentage', $sourceData)
                && $sourceData['contribution_percentage'] !== null
                && $sourceData['contribution_percentage'] !== '') {
                $source->contributionPercentageInput = (float) $sourceData['contribution_percentage'];
            }
            $source->save();
        }
    }

    /**
     * Crear sources automáticamente de forma proporcional
     *
     * IMPORTANTE: Los sources reflejan el CONSUMO REAL (peso de inputs), no el output final.
     * Esto permite calcular correctamente la merma como diferencia entre consumo y output.
     */
    protected function createSourcesAutomatically(ProductionOutput $output): void
    {
        $record = $output->productionRecord;
        if (! $record) {
            return;
        }

        // Obtener todos los inputs del proceso con relaciones cargadas
        $inputs = $record->inputs()->with('box.product')->get();
        $consumptions = $record->parentOutputConsumptions;

        // Obtener el consumo real total (peso de inputs + consumos del padre)
        // Esto es el peso REAL consumido, no el output final
        $totalInputWeight = $record->total_input_weight;

        if ($totalInputWeight <= 0) {
            return; // No hay inputs, no se pueden crear sources
        }

        // Distribuir proporcionalmente según el CONSUMO REAL
        // Los sources deben sumar el consumo real, no el output final
        $inputsByProduct = $inputs
            ->filter(fn ($input) => $input->box && $input->box->article_id)
            ->groupBy(fn ($input) => (int) $input->box->article_id);

        foreach ($inputsByProduct as $productId => $productInputs) {
            $contributedWeight = $productInputs->sum(fn ($input) => (float) ($input->box->net_weight ?? 0));
            if ($contributedWeight <= 0) {
                continue;
            }

            ProductionOutputSource::create([
                'production_output_id' => $output->id,
                'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
                'product_id' => (int) $productId,
                'production_output_consumption_id' => null,
                'contributed_weight_kg' => $contributedWeight,
                'contributed_boxes' => 0,
            ]);
        }

        foreach ($consumptions as $consumption) {
            $consumptionWeight = $consumption->consumed_weight_kg ?? 0;
            if ($consumptionWeight > 0) {
                ProductionOutputSource::create([
                    'production_output_id' => $output->id,
                    'source_type' => ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT,
                    'product_id' => null,
                    'production_output_consumption_id' => $consumption->id,
                    'contributed_weight_kg' => $consumptionWeight,
                    'contributed_boxes' => $consumption->consumed_boxes ?? 0,
                ]);
            }
        }
    }

    /**
     * Update a production output
     */
    public function update(ProductionOutput $output, array $data): ProductionOutput
    {
        return DB::transaction(function () use ($output, $data) {
            $sources = $data['sources'] ?? null;
            unset($data['sources']);

            $output->update($data);

            // Si se proporcionaron sources, reemplazar los existentes
            if ($sources !== null && is_array($sources)) {
                // Eliminar sources existentes
                $output->sources()->delete();
                // Crear nuevos sources
                $this->createSources($output, $sources);
            }

            $output->load([
                'productionRecord.production',
                'product',
                'sources.product',
                'sources.productionOutput.productionRecord',
                'sources.productionOutputConsumption.productionOutput.product',
                'sources.productionOutputConsumption.productionOutput.productionRecord.production',
            ]);

            return $output;
        });
    }

    /**
     * Delete a production output
     */
    public function delete(ProductionOutput $output): bool
    {
        return $output->delete();
    }

    /**
     * Create multiple production outputs
     */
    public function createMultiple(int $productionRecordId, array $outputsData): array
    {
        return DB::transaction(function () use ($productionRecordId, $outputsData) {
            $created = [];
            $errors = [];

            foreach ($outputsData as $index => $outputData) {
                try {
                    $output = $this->create([
                        'production_record_id' => $productionRecordId,
                        'product_id' => $outputData['product_id'],
                        'lot_id' => $outputData['lot_id'] ?? null,
                        'boxes' => $outputData['boxes'],
                        'weight_kg' => $outputData['weight_kg'],
                        'sources' => $outputData['sources'] ?? null,
                    ]);

                    $created[] = $output;
                } catch (\Exception $e) {
                    $errors[] = "Error en la salida #{$index}: ".$e->getMessage();
                }
            }

            return [
                'created' => $created,
                'errors' => $errors,
            ];
        });
    }
}
