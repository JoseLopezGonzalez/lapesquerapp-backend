<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostCatalog extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $table = 'cost_catalog';

    protected $fillable = [
        'name',
        'cost_type',
        'description',
        'default_unit',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Constantes para cost_type
     */
    const COST_TYPE_PRODUCTION = 'production';
    const COST_TYPE_LABOR = 'labor';
    const COST_TYPE_OPERATIONAL = 'operational';
    const COST_TYPE_PACKAGING = 'packaging';

    /**
     * Constantes para default_unit
     */
    const DEFAULT_UNIT_TOTAL = 'total';
    const DEFAULT_UNIT_PER_KG = 'per_kg';

    /**
     * Relación con ProductionCost (costes que usan este catálogo)
     */
    public function productionCosts()
    {
        return $this->hasMany(ProductionCost::class, 'cost_catalog_id');
    }

    /**
     * Scope para obtener solo costes activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para filtrar por tipo de coste
     */
    public function scopeOfType($query, string $costType)
    {
        return $query->where('cost_type', $costType);
    }
}
