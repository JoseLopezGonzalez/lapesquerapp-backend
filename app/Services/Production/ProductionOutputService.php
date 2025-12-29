<?php

namespace App\Services\Production;

use App\Models\ProductionOutput;
use App\Models\ProductionOutputSource;
use App\Models\ProductionRecord;
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

            $output->load(['productionRecord', 'product', 'sources']);

            return $output;
        });
    }

    /**
     * Crear sources manualmente
     */
    protected function createSources(ProductionOutput $output, array $sources): void
    {
        foreach ($sources as $sourceData) {
            ProductionOutputSource::create([
                'production_output_id' => $output->id,
                'source_type' => $sourceData['source_type'],
                'production_input_id' => $sourceData['production_input_id'] ?? null,
                'production_output_consumption_id' => $sourceData['production_output_consumption_id'] ?? null,
                'contributed_weight_kg' => $sourceData['contributed_weight_kg'] ?? null,
                'contribution_percentage' => $sourceData['contribution_percentage'] ?? null,
                'contributed_boxes' => $sourceData['contributed_boxes'] ?? 0,
            ]);
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
        if (!$record) {
            return;
        }

        // Obtener todos los inputs del proceso con relaciones cargadas
        $inputs = $record->inputs()->with('box')->get();
        $consumptions = $record->parentOutputConsumptions;

        // Obtener el consumo real total (peso de inputs + consumos del padre)
        // Esto es el peso REAL consumido, no el output final
        $totalInputWeight = $record->total_input_weight;

        if ($totalInputWeight <= 0) {
            return; // No hay inputs, no se pueden crear sources
        }

        // Distribuir proporcionalmente según el CONSUMO REAL
        // Los sources deben sumar el consumo real, no el output final
        foreach ($inputs as $input) {
            $inputWeight = $input->box->net_weight ?? 0;
            if ($inputWeight > 0) {
                // Porcentaje del consumo real que representa este input
                $percentage = ($inputWeight / $totalInputWeight) * 100;
                // El peso contribuido es el peso REAL consumido de este input
                $contributedWeight = $inputWeight;

                ProductionOutputSource::create([
                    'production_output_id' => $output->id,
                    'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_BOX,
                    'production_input_id' => $input->id,
                    'production_output_consumption_id' => null,
                    'contributed_weight_kg' => $contributedWeight,
                    'contribution_percentage' => $percentage,
                    'contributed_boxes' => 0,
                ]);
            }
        }

        foreach ($consumptions as $consumption) {
            $consumptionWeight = $consumption->consumed_weight_kg ?? 0;
            if ($consumptionWeight > 0) {
                // Porcentaje del consumo real que representa este consumo
                $percentage = ($consumptionWeight / $totalInputWeight) * 100;
                // El peso contribuido es el peso REAL consumido
                $contributedWeight = $consumptionWeight;

                ProductionOutputSource::create([
                    'production_output_id' => $output->id,
                    'source_type' => ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT,
                    'production_input_id' => null,
                    'production_output_consumption_id' => $consumption->id,
                    'contributed_weight_kg' => $contributedWeight,
                    'contribution_percentage' => $percentage,
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

            $output->load(['productionRecord', 'product', 'sources']);

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
                    $errors[] = "Error en la salida #{$index}: " . $e->getMessage();
                }
            }

            return [
                'created' => $created,
                'errors' => $errors,
            ];
        });
    }
}

