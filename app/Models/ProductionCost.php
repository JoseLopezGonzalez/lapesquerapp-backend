<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCost extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'production_record_id',
        'production_id',
        'cost_catalog_id',
        'cost_type',
        'name',
        'description',
        'total_cost',
        'cost_per_kg',
        'distribution_unit',
        'cost_date',
    ];

    protected $casts = [
        'total_cost' => 'decimal:2',
        'cost_per_kg' => 'decimal:2',
        'cost_date' => 'date',
    ];

    /**
     * Constantes para cost_type
     */
    const COST_TYPE_PRODUCTION = 'production';
    const COST_TYPE_LABOR = 'labor';
    const COST_TYPE_OPERATIONAL = 'operational';
    const COST_TYPE_PACKAGING = 'packaging';

    /**
     * Boot del modelo - Validaciones
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($cost) {
            $cost->validateCostRules();
        });
    }

    /**
     * Validar reglas de ProductionCost
     */
    protected function validateCostRules(): void
    {
        // Validar que solo uno de production_record_id o production_id esté presente
        if ($this->production_record_id !== null && $this->production_id !== null) {
            throw new \InvalidArgumentException(
                'Solo uno de production_record_id o production_id debe estar presente.'
            );
        }

        if ($this->production_record_id === null && $this->production_id === null) {
            throw new \InvalidArgumentException(
                'Debe especificarse production_record_id o production_id.'
            );
        }

        // Validar que se especifique O bien total_cost O bien cost_per_kg
        if ($this->total_cost === null && $this->cost_per_kg === null) {
            throw new \InvalidArgumentException(
                'Se debe especificar O bien total_cost O bien cost_per_kg.'
            );
        }

        if ($this->total_cost !== null && $this->cost_per_kg !== null) {
            throw new \InvalidArgumentException(
                'Solo uno de total_cost o cost_per_kg debe estar presente.'
            );
        }

        // Si viene del catálogo, obtener name y cost_type automáticamente
        if ($this->cost_catalog_id !== null) {
            $catalog = CostCatalog::find($this->cost_catalog_id);
            if ($catalog) {
                // Si name no está especificado o está vacío, usar el del catálogo
                if (empty($this->name)) {
                    $this->name = $catalog->name;
                }
                // Si cost_type no está especificado, usar el del catálogo
                if (empty($this->cost_type)) {
                    $this->cost_type = $catalog->cost_type;
                }
            } else {
                // El catálogo no existe, pero cost_catalog_id está presente
                throw new \InvalidArgumentException(
                    "El coste del catálogo con ID {$this->cost_catalog_id} no existe."
                );
            }
        } else {
            // Si no viene del catálogo, name y cost_type son obligatorios
            if (empty($this->name)) {
                throw new \InvalidArgumentException(
                    'Si cost_catalog_id es null, name debe estar presente.'
                );
            }
            if (empty($this->cost_type)) {
                throw new \InvalidArgumentException(
                    'Si cost_catalog_id es null, cost_type debe estar presente.'
                );
            }
        }
    }

    /**
     * Relación con ProductionRecord (si es coste de proceso)
     */
    public function productionRecord()
    {
        return $this->belongsTo(ProductionRecord::class, 'production_record_id');
    }

    /**
     * Relación con Production (si es coste de lote)
     */
    public function production()
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    /**
     * Relación con CostCatalog (si viene del catálogo)
     */
    public function costCatalog()
    {
        return $this->belongsTo(CostCatalog::class, 'cost_catalog_id');
    }

    /**
     * Calcular el coste total efectivo
     * Si es cost_per_kg, se multiplica por el peso total de outputs
     */
    public function getEffectiveTotalCostAttribute(): ?float
    {
        if ($this->total_cost !== null) {
            return $this->total_cost;
        }

        if ($this->cost_per_kg !== null) {
            // Obtener peso total de outputs
            $totalWeight = 0;

            if ($this->production_record_id !== null) {
                // Coste de proceso: peso total de outputs del proceso
                $record = $this->productionRecord;
                if ($record) {
                    $totalWeight = $record->total_output_weight;
                }
            } elseif ($this->production_id !== null) {
                // Coste de producción: peso total de outputs finales del lote
                $production = $this->production;
                if ($production) {
                    // Usar el método público getFinalNodesOutputsTotals() o calcular directamente
                    // Como getFinalNodesOutputs() es privado, calculamos directamente
                    $allRecords = $production->records()
                        ->with(['inputs', 'children', 'outputs'])
                        ->get();
                    
                    $finalRecords = $allRecords->filter(function ($record) {
                        return $record->isFinal();
                    });
                    
                    $finalOutputs = collect();
                    foreach ($finalRecords as $record) {
                        $finalOutputs = $finalOutputs->merge($record->outputs);
                    }
                    
                    $totalWeight = $finalOutputs->sum('weight_kg');
                }
            }

            return $totalWeight * $this->cost_per_kg;
        }

        return null;
    }
}
