<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOutputSource extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'production_output_id',
        'source_type',
        'product_id',
        'production_output_consumption_id',
        'contributed_weight_kg',
        'contributed_boxes',
        'contribution_percentage',
    ];

    protected $casts = [
        'contributed_weight_kg' => 'decimal:2',
        'contributed_boxes' => 'integer',
        'contribution_percentage' => 'decimal:2',
    ];

    /**
     * Constantes para source_type
     */
    const SOURCE_TYPE_STOCK_PRODUCT = 'stock_product';

    const SOURCE_TYPE_PARENT_OUTPUT = 'parent_output';

    /**
     * Boot del modelo - Validaciones
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($source) {
            $source->validateSourceRules();
        });
    }

    /**
     * Validar reglas de ProductionOutputSource
     */
    protected function validateSourceRules(): void
    {
        // Validar que se especifique O bien peso O bien porcentaje
        if ($this->contributed_weight_kg === null && $this->contribution_percentage === null) {
            throw new \InvalidArgumentException(
                'Se debe especificar O bien contributed_weight_kg O bien contribution_percentage.'
            );
        }

        // IMPORTANTE: Los sources reflejan el CONSUMO REAL, no el output final
        // El porcentaje se calcula sobre el consumo total (total_input_weight del proceso)
        // El peso contribuido es el peso REAL consumido de esta fuente

        // Si se especifica porcentaje, calcular el peso desde el consumo real
        if ($this->contribution_percentage !== null && $this->contributed_weight_kg === null) {
            // Cargar output y record si no están cargados
            if (! $this->relationLoaded('productionOutput') && $this->production_output_id) {
                $this->load('productionOutput.productionRecord');
            }
            $output = $this->productionOutput;
            if ($output && $output->productionRecord) {
                $record = $output->productionRecord;
                $totalInputWeight = $record->total_input_weight;
                if ($totalInputWeight > 0) {
                    // El peso contribuido es el porcentaje del consumo real
                    $this->contributed_weight_kg = ($totalInputWeight * $this->contribution_percentage) / 100;
                }
            }
        }

        // Si se especifica peso, calcular el porcentaje sobre el consumo real
        if ($this->contributed_weight_kg !== null && $this->contribution_percentage === null) {
            // Cargar output y record si no están cargados
            if (! $this->relationLoaded('productionOutput') && $this->production_output_id) {
                $this->load('productionOutput.productionRecord');
            }
            $output = $this->productionOutput;
            if ($output && $output->productionRecord) {
                $record = $output->productionRecord;
                $totalInputWeight = $record->total_input_weight;
                if ($totalInputWeight > 0) {
                    // El porcentaje es sobre el consumo real, no sobre el output
                    $this->contribution_percentage = ($this->contributed_weight_kg / $totalInputWeight) * 100;
                }
            }
        }

        // Validar consistencia de source_type
        if ($this->source_type === self::SOURCE_TYPE_STOCK_PRODUCT) {
            if ($this->product_id === null) {
                throw new \InvalidArgumentException(
                    'Si source_type es "stock_product", product_id debe estar presente.'
                );
            }
            if ($this->production_output_consumption_id !== null) {
                throw new \InvalidArgumentException(
                    'Si source_type es "stock_product", production_output_consumption_id debe ser null.'
                );
            }

            if (! $this->relationLoaded('productionOutput') && $this->production_output_id) {
                $this->load('productionOutput.productionRecord');
            }

            $record = $this->productionOutput?->productionRecord;
            // Use a query so we never iterate ProductionInput models without box eager-loaded (lazy loading may be disabled).
            $productExistsInInputs = $record !== null
                && $record->inputs()
                    ->whereHas('box', fn ($query) => $query->where('article_id', (int) $this->product_id))
                    ->exists();

            if (! $productExistsInInputs) {
                throw new \InvalidArgumentException(
                    'El product_id de una source stock_product debe existir entre los inputs del proceso.'
                );
            }
        } elseif ($this->source_type === self::SOURCE_TYPE_PARENT_OUTPUT) {
            if ($this->production_output_consumption_id === null) {
                throw new \InvalidArgumentException(
                    'Si source_type es "parent_output", production_output_consumption_id debe estar presente.'
                );
            }
            if ($this->product_id !== null) {
                throw new \InvalidArgumentException(
                    'Si source_type es "parent_output", product_id debe ser null.'
                );
            }
        }
    }

    /**
     * Relación con ProductionOutput
     */
    public function productionOutput()
    {
        return $this->belongsTo(ProductionOutput::class, 'production_output_id');
    }

    /**
     * Relación con Product (si es stock_product)
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Relación con ProductionOutputConsumption (si es parent_output)
     */
    public function productionOutputConsumption()
    {
        return $this->belongsTo(ProductionOutputConsumption::class, 'production_output_consumption_id');
    }

    /**
     * Obtener el coste por kg de esta fuente
     */
    public function getSourceCostPerKgAttribute(): ?float
    {
        if ($this->source_type === self::SOURCE_TYPE_STOCK_PRODUCT) {
            if (! $this->relationLoaded('productionOutput') && $this->production_output_id) {
                $this->load('productionOutput.productionRecord');
            }

            $output = $this->productionOutput;
            $record = $output?->productionRecord;
            if (! $record || ! $this->product_id) {
                return null;
            }

            $inputs = $record->inputs()
                ->whereHas('box', fn ($query) => $query->where('article_id', $this->product_id))
                ->with('box')
                ->get();

            $totalWeight = 0.0;
            $totalCost = 0.0;

            foreach ($inputs as $input) {
                $box = $input->box;
                $weight = (float) ($box?->net_weight ?? 0);
                $costPerKg = $box?->cost_per_kg;

                if ($weight <= 0 || $costPerKg === null) {
                    continue;
                }

                $totalWeight += $weight;
                $totalCost += $weight * (float) $costPerKg;
            }

            if ($totalWeight <= 0) {
                return null;
            }

            return $totalCost / $totalWeight;
        } elseif ($this->source_type === self::SOURCE_TYPE_PARENT_OUTPUT) {
            // Coste desde el output del padre (se calculará recursivamente)
            if (! $this->relationLoaded('productionOutputConsumption') && $this->production_output_consumption_id) {
                $this->load('productionOutputConsumption.productionOutput');
            }
            $consumption = $this->productionOutputConsumption;
            if (! $consumption || ! $consumption->productionOutput) {
                return null;
            }

            // Obtener el output padre
            $parentOutput = $consumption->productionOutput;

            // Prevenir recursión infinita: si el output padre es el mismo que el actual, retornar null
            if ($this->production_output_id && $parentOutput->id == $this->production_output_id) {
                return null; // Ciclo detectado
            }

            // Calcular coste del output padre (con protección contra recursión automática)
            return $parentOutput->cost_per_kg;
        }

        return null;
    }

    /**
     * Obtener el coste total que aporta esta fuente
     *
     * IMPORTANTE: El peso contribuido refleja el CONSUMO REAL, no el output final
     */
    public function getSourceTotalCostAttribute(): ?float
    {
        $costPerKg = $this->source_cost_per_kg;
        if ($costPerKg === null) {
            return null;
        }

        // Si no tenemos el peso contribuido, intentar calcularlo desde el porcentaje
        // IMPORTANTE: El peso se calcula sobre el consumo real, no sobre el output
        $weight = $this->contributed_weight_kg;
        if ($weight === null || $weight <= 0) {
            if ($this->contribution_percentage !== null) {
                if (! $this->relationLoaded('productionOutput') && $this->production_output_id) {
                    $this->load('productionOutput.productionRecord');
                }
                $output = $this->productionOutput;
                if ($output && $output->productionRecord) {
                    $record = $output->productionRecord;
                    $totalInputWeight = $record->total_input_weight;
                    if ($totalInputWeight > 0) {
                        // El peso se calcula sobre el consumo real
                        $weight = ($totalInputWeight * $this->contribution_percentage) / 100;
                    }
                }
            }
        }

        if ($weight === null || $weight <= 0) {
            return null;
        }

        return $weight * $costPerKg;
    }
}
