<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOutputSource extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    /**
     * Solo para entrada en save: si viene `contribution_percentage` en el payload de API,
     * el servicio asigna aquí el valor; no se persiste en BD.
     */
    public ?float $contributionPercentageInput = null;

    protected $fillable = [
        'production_output_id',
        'source_type',
        'product_id',
        'production_output_consumption_id',
        'contributed_weight_kg',
        'contributed_boxes',
    ];

    protected $casts = [
        'contributed_weight_kg' => 'decimal:2',
        'contributed_boxes' => 'integer',
    ];

    /**
     * Constantes para source_type
     */
    const SOURCE_TYPE_STOCK_PRODUCT = 'stock_product';

    const SOURCE_TYPE_PARENT_OUTPUT = 'parent_output';

    /**
     * Porcentaje de la fuente en la mezcla de fuentes del mismo output (suma de % = 100).
     * Denominador: suma de contributed_weight_kg de todas las fuentes de ese production_output.
     */
    public static function contributionPercentageOfSourceMix(?float $contributedKg, float $totalContributedKgOnOutput): ?float
    {
        if ($contributedKg === null || $totalContributedKgOnOutput <= 0) {
            return null;
        }

        return ($contributedKg / $totalContributedKgOnOutput) * 100;
    }

    /**
     * Resolver kg efectivos desde payload de source + peso del output (sync / validación).
     */
    public static function resolveContributedWeightKgFromSourcePayload(array $sourceData, float $outputWeightKg): float
    {
        $kg = $sourceData['contributed_weight_kg'] ?? null;
        if ($kg !== null && $kg !== '') {
            return (float) $kg;
        }

        $pct = $sourceData['contribution_percentage'] ?? null;
        if ($pct !== null && $pct !== '' && $outputWeightKg > 0) {
            return ($outputWeightKg * (float) $pct) / 100;
        }

        return 0.0;
    }

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
        $hasKg = $this->contributed_weight_kg !== null && $this->contributed_weight_kg !== '';
        $hasPctInput = $this->contributionPercentageInput !== null;

        if (! $hasKg && ! $hasPctInput) {
            throw new \InvalidArgumentException(
                'Se debe especificar O bien contributed_weight_kg O bien contribution_percentage.'
            );
        }

        if ($hasPctInput && (! $hasKg || $this->contributed_weight_kg === null)) {
            if (! $this->relationLoaded('productionOutput') && $this->production_output_id) {
                $this->load('productionOutput');
            }
            $output = $this->productionOutput;
            $outputWeight = (float) ($output?->weight_kg ?? 0);
            if ($outputWeight <= 0) {
                throw new \InvalidArgumentException(
                    'Para usar contribution_percentage el output debe tener weight_kg mayor que cero.'
                );
            }
            $this->contributed_weight_kg = ($outputWeight * $this->contributionPercentageInput) / 100;
        }

        $this->contributionPercentageInput = null;

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

            if ($this->production_output_id) {
                $this->loadMissing('productionOutput.productionRecord');
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
            if ($this->production_output_id) {
                $this->loadMissing('productionOutput.productionRecord');
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
     */
    public function getSourceTotalCostAttribute(): ?float
    {
        $costPerKg = $this->source_cost_per_kg;
        if ($costPerKg === null) {
            return null;
        }

        $weight = (float) ($this->contributed_weight_kg ?? 0);
        if ($weight <= 0) {
            return null;
        }

        return $weight * $costPerKg;
    }
}
