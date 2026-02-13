# Propuesta de Implementación: Trazabilidad de Costes en Producciones

## 📋 Resumen Ejecutivo

Este documento propone una implementación para dotar de **proveniencia y trazabilidad de costes** a los resultados finales de cada nodo de producción. El objetivo es rastrear desde qué productos originarios (con sus costes) derivan los productos resultantes, considerando mermas y rendimientos, y preparar la estructura para futuros costes adicionales (producción, personal, operativos, envases).

**Fecha**: 2025-01-XX  
**Versión**: v2 (v1 está deprecado)  
**Estado**: Propuesta de diseño

---

## 🎯 Objetivos

1. **Trazabilidad de Proveniencia**: Registrar de qué productos y cantidades originarias derivan los productos resultantes de cada nodo
2. **Cálculo de Costes**: Calcular el coste por kg de productos resultantes basándose en:
   - Costes de materias primas consumidas (desde recepciones)
   - Costes de productos intermedios consumidos (desde nodos padres)
   - Mermas y rendimientos en el proceso
3. **Preparación para Costes Futuros**: Estructura extensible para agregar:
   - Costes de producción
   - Costes de personal
   - Costes operativos
   - Costes de envases
4. **Compatibilidad**: Mantener compatibilidad con el sistema actual de costes de recepciones

---

## 🔍 Análisis del Estado Actual

### Sistema de Costes Actual (Recepciones)

**Estructura existente**:
- `raw_material_reception_products.price` → Precio por kg del producto en la recepción
- `Box::getCostPerKgAttribute()` → Obtiene coste desde recepción a través del palet
- `Box::getTotalCostAttribute()` → Calcula coste total (net_weight × cost_per_kg)
- `Pallet::getCostPerKgAttribute()` → Media ponderada de costes de cajas
- `Pallet::getTotalCostAttribute()` → Suma de costes de cajas

**Limitación actual**: Solo se tienen costes para productos que provienen de recepciones. Los productos resultantes de producciones **NO tienen coste**.

### Tipos de Costes en el Sistema

El sistema debe manejar **tres tipos de costes distintos**:

1. **Costes de Recepciones** (Ya implementado):
   - Productos que provienen directamente de recepciones de materia prima
   - Se calculan desde `raw_material_reception_products.price`
   - Se propagan a `Box` y `Pallet` mediante accessors
   - **Ejemplo**: Caja de "Atún entero" con coste de 10€/kg desde recepción

2. **Costes de Productos Intermedios** (A implementar):
   - Productos resultantes de procesos intermedios que **NO llegan a registrarse como palets/cajas**
   - Son `ProductionOutput` que se consumen por procesos hijos pero no generan stock físico
   - **Ejemplo**: "Atún eviscerado" producido en un proceso que se consume inmediatamente por otro proceso

3. **Costes de Productos Finales** (A implementar):
   - Productos resultantes finales que **SÍ se registran como palets/cajas** en stock
   - Son `ProductionOutput` de nodos finales que generan stock físico
   - **Ejemplo**: "Filetes de atún" que terminan en palets almacenados

### Sistema de Producciones Actual (v2)

**Estructura relacional**:
- `Production` → Lote de producción
- `ProductionRecord` → Proceso individual (árbol jerárquico)
- `ProductionInput` → Cajas consumidas desde stock (vincula `Box`)
- `ProductionOutput` → Productos producidos (declaración de cantidad/peso)
- `ProductionOutputConsumption` → Consumo de outputs del padre por procesos hijos

**Flujo actual**:
1. Se gastan productos de stock (cajas con coste de recepción) → `ProductionInput`
2. Se realizan cambios/transformaciones → `ProductionRecord` con inputs/outputs
3. Los procesos hijos pueden consumir outputs del padre → `ProductionOutputConsumption`
4. Se registran productos finales → `ProductionOutput` en nodos finales
5. Se detecta stock final en la app → Cajas con `lot` coincidente

**Problema identificado**: 
- Los `ProductionOutput` no tienen información de proveniencia
- No se rastrea qué inputs (cajas o outputs del padre) generaron cada output
- No se calcula el coste de los productos resultantes

---

## 💡 Propuesta de Implementación

### 1. Nueva Tabla: `production_output_sources`

Esta tabla registrará la **proveniencia** de cada output, es decir, de qué inputs (cajas o outputs del padre) deriva cada producto resultante.

**Migración propuesta**:

```php
Schema::create('production_output_sources', function (Blueprint $table) {
    $table->id();
    
    // Output al que pertenece esta fuente
    $table->unsignedBigInteger('production_output_id');
    $table->foreign('production_output_id')
          ->references('id')
          ->on('production_outputs')
          ->onDelete('cascade');
    
    // Tipo de fuente: 'stock_box' o 'parent_output'
    $table->enum('source_type', ['stock_box', 'parent_output']);
    
    // Si es stock_box: referencia a ProductionInput
    $table->unsignedBigInteger('production_input_id')->nullable();
    $table->foreign('production_input_id')
          ->references('id')
          ->on('production_inputs')
          ->onDelete('cascade');
    
    // Si es parent_output: referencia a ProductionOutputConsumption
    $table->unsignedBigInteger('production_output_consumption_id')->nullable();
    $table->foreign('production_output_consumption_id')
          ->references('id')
          ->on('production_output_consumptions')
          ->onDelete('cascade');
    
    // Cantidad de peso (kg) que aporta esta fuente al output
    // ⚠️ Puede ser null si se especifica solo el porcentaje
    $table->decimal('contributed_weight_kg', 10, 2)->nullable();
    
    // Cantidad de cajas que aporta esta fuente (si aplica)
    $table->integer('contributed_boxes')->default(0);
    
    // Porcentaje del output que proviene de esta fuente (0-100)
    // ⚠️ Puede ser null si se especifica solo el peso
    $table->decimal('contribution_percentage', 5, 2)->nullable();
    
    // ⚠️ IMPORTANTE: Se debe especificar O bien contributed_weight_kg O bien contribution_percentage
    // Si se especifica uno, el otro se calcula automáticamente
    
    $table->timestamps();
    
    // Índices
    $table->index('production_output_id');
    $table->index(['source_type', 'production_input_id']);
    $table->index(['source_type', 'production_output_consumption_id']);
    
    // Constraints: Solo uno de los dos IDs debe estar presente según source_type
    // Esto se validará a nivel de aplicación
});
```

**Campos explicados**:
- `production_output_id`: El output al que contribuye esta fuente
- `source_type`: Tipo de fuente (`'stock_box'` = caja del stock, `'parent_output'` = output del padre)
- `production_input_id`: Si es `stock_box`, referencia al `ProductionInput` (caja consumida)
- `production_output_consumption_id`: Si es `parent_output`, referencia al `ProductionOutputConsumption` (consumo del padre)
- `contributed_weight_kg`: Peso en kg que esta fuente aporta al output (nullable, se calcula si se especifica porcentaje)
- `contributed_boxes`: Cantidad de cajas que aporta (si aplica)
- `contribution_percentage`: Porcentaje del output total que proviene de esta fuente (nullable, se calcula si se especifica peso)

**⚠️ Regla de especificación**:
- Se debe especificar **O bien** `contributed_weight_kg` **O bien** `contribution_percentage`
- Si se especifica uno, el otro se calcula automáticamente:
  - Si se especifica `contribution_percentage`: `contributed_weight_kg = (output.weight_kg × contribution_percentage) / 100`
  - Si se especifica `contributed_weight_kg`: `contribution_percentage = (contributed_weight_kg / output.weight_kg) × 100`

**Ejemplo de datos**:
```
Output: 100kg de "Filetes de atún" (ID: 5)
Fuentes:
  - Source 1: production_input_id=10 (caja de 30kg) → contributed_weight_kg=30, contribution_percentage=30%
  - Source 2: production_input_id=11 (caja de 25kg) → contributed_weight_kg=25, contribution_percentage=25%
  - Source 3: production_output_consumption_id=3 (consumo de 45kg del padre) → contributed_weight_kg=45, contribution_percentage=45%
```

### 2. Nueva Tabla: `cost_catalog` (Catálogo de Costes)

Esta tabla almacenará un **catálogo de costes comunes** para evitar inconsistencias en nombres y facilitar el análisis.

**Migración propuesta**:

```php
Schema::create('cost_catalog', function (Blueprint $table) {
    $table->id();
    
    // Nombre del coste (único)
    $table->string('name')->unique();
    
    // Tipo de coste (categoría)
    $table->enum('cost_type', [
        'production',    // Costes de producción (maquinaria, energía, etc.)
        'labor',         // Costes de personal
        'operational',   // Costes operativos (mantenimiento, servicios, etc.)
        'packaging'      // Costes de envases
    ]);
    
    // Descripción del coste
    $table->text('description')->nullable();
    
    // Unidad por defecto (total o per_kg)
    // Indica cómo se suele especificar este coste
    $table->enum('default_unit', ['total', 'per_kg'])->default('total');
    
    // Si está activo (permite desactivar costes sin eliminar)
    $table->boolean('is_active')->default(true);
    
    $table->timestamps();
    
    // Índices
    $table->index('cost_type');
    $table->index('is_active');
});
```

**Campos explicados**:
- `name`: Nombre único del coste en el catálogo (ej: "Energía eléctrica", "Mantenimiento máquina")
- `cost_type`: Categoría del coste
- `description`: Descripción opcional del coste
- `default_unit`: Unidad por defecto (sugerencia, pero el usuario puede cambiarla)
- `is_active`: Permite desactivar costes sin eliminarlos

**Ejemplos de registros en el catálogo**:
```
ID | Name                        | cost_type    | default_unit | description
1  | Mantenimiento máquina       | production   | total       | Mantenimiento preventivo de maquinaria
2  | Energía eléctrica           | operational  | per_kg      | Consumo eléctrico del proceso
3  | Agua industrial             | operational  | per_kg      | Consumo de agua industrial
4  | Personal producción        | labor        | total       | Personal dedicado a producción
5  | Limpieza general            | operational  | total       | Servicio de limpieza
6  | Envases plástico           | packaging    | per_kg      | Coste de envases plásticos
7  | Supervisión                | labor        | total       | Personal de supervisión
8  | Control de calidad         | labor        | total       | Personal de control de calidad
```

### 3. Nueva Tabla: `production_costs`

Esta tabla almacenará los **costes adicionales** que se agregarán al coste de materias primas. Los costes pueden estar a **nivel de proceso** (`production_record_id`) o a **nivel de producción** (`production_id`).

**Migración propuesta**:

```php
Schema::create('production_costs', function (Blueprint $table) {
    $table->id();
    
    // ⚠️ IMPORTANTE: Solo uno de los dos debe estar presente
    // Nivel de proceso (coste específico de un proceso)
    $table->unsignedBigInteger('production_record_id')->nullable();
    $table->foreign('production_record_id')
          ->references('id')
          ->on('production_records')
          ->onDelete('cascade');
    
    // Nivel de producción (coste general del lote completo)
    $table->unsignedBigInteger('production_id')->nullable();
    $table->foreign('production_id')
          ->references('id')
          ->on('productions')
          ->onDelete('cascade');
    
    // ⚠️ IMPORTANTE: Referencia al catálogo de costes (si viene del catálogo)
    $table->unsignedBigInteger('cost_catalog_id')->nullable();
    $table->foreign('cost_catalog_id')
          ->references('id')
          ->on('cost_catalog')
          ->onDelete('set null'); // Si se elimina del catálogo, se mantiene el registro pero sin referencia
    
    // Tipo de coste (categoría general)
    // Se obtiene del catálogo si cost_catalog_id está presente, sino se especifica manualmente
    $table->enum('cost_type', [
        'production',    // Costes de producción (maquinaria, energía, etc.)
        'labor',         // Costes de personal
        'operational',   // Costes operativos (mantenimiento, servicios, etc.)
        'packaging'      // Costes de envases
    ]);
    
    // ⚠️ IMPORTANTE: Nombre del coste
    // - Si cost_catalog_id está presente: Se obtiene del catálogo (pero se puede sobrescribir)
    // - Si cost_catalog_id es null: Nombre libre (coste ad-hoc)
    // Esto permite flexibilidad para costes especiales no catalogados
    $table->string('name');
    $table->string('description')->nullable(); // Descripción adicional opcional
    
    // ⚠️ IMPORTANTE: El coste puede especificarse de dos formas:
    // 1. Coste total (total_cost): Se distribuye proporcionalmente al peso de outputs
    // 2. Coste por kg (cost_per_kg): Se multiplica por el peso total de outputs del proceso/producción
    
    // Coste total (si se especifica, cost_per_kg debe ser null)
    $table->decimal('total_cost', 10, 2)->nullable();
    
    // Coste por kg (si se especifica, total_cost debe ser null)
    // Se multiplica por el peso total de outputs para obtener el coste total
    $table->decimal('cost_per_kg', 10, 2)->nullable();
    
    // Unidad de medida para distribuir el coste (opcional, solo si total_cost está presente)
    // Si es null, se distribuye proporcionalmente al peso de outputs
    $table->string('distribution_unit')->nullable(); // 'per_kg', 'per_box', 'per_hour', etc.
    
    // Fecha del coste
    $table->date('cost_date')->nullable();
    
    $table->timestamps();
    
    // Índices
    $table->index('production_record_id');
    $table->index('production_id');
    $table->index('cost_catalog_id');
    $table->index('cost_type');
    
    // Constraints: Solo uno de los dos IDs debe estar presente
    // Esto se validará a nivel de aplicación
});
```

**Campos explicados**:
- `production_record_id`: Si el coste es específico de un proceso (nullable)
- `production_id`: Si el coste es general del lote completo (nullable)
- `cost_catalog_id`: **Referencia al catálogo de costes** (nullable) - Si viene del catálogo, se usa el nombre estándar
- `cost_type`: Tipo de coste (categoría: producción, personal, operativos, envases) - Se obtiene del catálogo si está presente
- `name`: **Nombre del coste** - Se obtiene del catálogo si `cost_catalog_id` está presente, sino es nombre libre (ad-hoc)
- `description`: Descripción adicional opcional del coste
- `total_cost`: Coste total a distribuir (nullable, se usa si se especifica coste total)
- `cost_per_kg`: Coste por kg (nullable, se usa si se especifica coste por kg)
- `distribution_unit`: Unidad para distribuir (opcional, solo si `total_cost` está presente)

**⚠️ Reglas de especificación de coste**:
- Se debe especificar **O bien** `total_cost` **O bien** `cost_per_kg` (no ambos, no ninguno)
- Si se especifica `cost_per_kg`:
  - Para costes de proceso: Se multiplica por el peso total de outputs del proceso
  - Para costes de producción: Se multiplica por el peso total de outputs finales del lote
  - El resultado se distribuye proporcionalmente entre los outputs
- Si se especifica `total_cost`:
  - Se distribuye directamente proporcionalmente al peso de outputs

**⚠️ Reglas de uso del catálogo**:
- **Opción A - Usar catálogo** (Recomendado):
  - Se especifica `cost_catalog_id`
  - El `name` y `cost_type` se obtienen automáticamente del catálogo
  - El usuario puede sobrescribir el `name` si necesita una variación específica
  - Ventaja: Consistencia y facilita análisis
  
- **Opción B - Coste ad-hoc**:
  - `cost_catalog_id` es null
  - El usuario especifica `name` y `cost_type` manualmente
  - Ventaja: Flexibilidad para costes especiales no catalogados

**Distribución de costes**:
- **Costes a nivel de proceso**: Se distribuyen proporcionalmente entre los outputs de ese proceso
- **Costes a nivel de producción**: Se distribuyen proporcionalmente entre todos los outputs finales del lote

**Ejemplos de registros en `production_costs`**:

**Ejemplo 1: Coste desde catálogo (recomendado)**:
```
production_record_id: 5
production_id: null
cost_catalog_id: 1  // "Mantenimiento máquina" del catálogo
cost_type: 'production'  // Se obtiene del catálogo
name: 'Mantenimiento máquina'  // Se obtiene del catálogo
description: 'Mantenimiento preventivo mensual'
total_cost: 500.00
cost_per_kg: null
```

**Ejemplo 2: Coste desde catálogo con nombre personalizado**:
```
production_record_id: 5
production_id: null
cost_catalog_id: 2  // "Energía eléctrica" del catálogo
cost_type: 'operational'  // Se obtiene del catálogo
name: 'Energía eléctrica - Proceso fileteado'  // Sobrescrito para especificar
description: 'Consumo eléctrico específico del proceso'
total_cost: null
cost_per_kg: 0.50
```

**Ejemplo 3: Coste ad-hoc (no está en catálogo)**:
```
production_record_id: 5
production_id: null
cost_catalog_id: null  // No viene del catálogo
cost_type: 'operational'  // Especificado manualmente
name: 'Servicio especial de limpieza'  // Nombre libre
description: 'Limpieza especial por inspección'
total_cost: 200.00
cost_per_kg: null
```

**Ejemplo 4: Coste del lote desde catálogo**:
```
production_record_id: null
production_id: 10
cost_catalog_id: 7  // "Supervisión" del catálogo
cost_type: 'labor'  // Se obtiene del catálogo
name: 'Supervisión'  // Se obtiene del catálogo
description: 'Personal de supervisión dedicado al lote completo'
total_cost: 1500.00
cost_per_kg: null
```

**Nota**: Esta tabla se implementará en una fase futura. Por ahora, la estructura estará preparada pero no se utilizará.

### 3. Modelo: `ProductionOutputSource`

**Archivo**: `app/Models/ProductionOutputSource.php`

```php
<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOutputSource extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'production_output_id',
        'source_type',
        'production_input_id',
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

        // Si se especifica porcentaje, calcular el peso
        if ($this->contribution_percentage !== null && $this->contributed_weight_kg === null) {
            $output = $this->productionOutput;
            if ($output && $output->weight_kg > 0) {
                $this->contributed_weight_kg = ($output->weight_kg * $this->contribution_percentage) / 100;
            }
        }

        // Si se especifica peso, calcular el porcentaje
        if ($this->contributed_weight_kg !== null && $this->contribution_percentage === null) {
            $output = $this->productionOutput;
            if ($output && $output->weight_kg > 0) {
                $this->contribution_percentage = ($this->contributed_weight_kg / $output->weight_kg) * 100;
            }
        }

        // Validar consistencia de source_type
        if ($this->source_type === self::SOURCE_TYPE_STOCK_BOX) {
            if ($this->production_input_id === null) {
                throw new \InvalidArgumentException(
                    'Si source_type es "stock_box", production_input_id debe estar presente.'
                );
            }
            if ($this->production_output_consumption_id !== null) {
                throw new \InvalidArgumentException(
                    'Si source_type es "stock_box", production_output_consumption_id debe ser null.'
                );
            }
        } elseif ($this->source_type === self::SOURCE_TYPE_PARENT_OUTPUT) {
            if ($this->production_output_consumption_id === null) {
                throw new \InvalidArgumentException(
                    'Si source_type es "parent_output", production_output_consumption_id debe estar presente.'
                );
            }
            if ($this->production_input_id !== null) {
                throw new \InvalidArgumentException(
                    'Si source_type es "parent_output", production_input_id debe ser null.'
                );
            }
        }
    }

    // Constantes para source_type
    const SOURCE_TYPE_STOCK_BOX = 'stock_box';
    const SOURCE_TYPE_PARENT_OUTPUT = 'parent_output';

    /**
     * Relación con ProductionOutput
     */
    public function productionOutput()
    {
        return $this->belongsTo(ProductionOutput::class, 'production_output_id');
    }

    /**
     * Relación con ProductionInput (si es stock_box)
     */
    public function productionInput()
    {
        return $this->belongsTo(ProductionInput::class, 'production_input_id');
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
        if ($this->source_type === self::SOURCE_TYPE_STOCK_BOX) {
            // Coste desde la caja del stock
            $input = $this->productionInput;
            if (!$input || !$input->box) {
                return null;
            }
            return $input->box->cost_per_kg;
        } elseif ($this->source_type === self::SOURCE_TYPE_PARENT_OUTPUT) {
            // Coste desde el output del padre (se calculará recursivamente)
            $consumption = $this->productionOutputConsumption;
            if (!$consumption || !$consumption->productionOutput) {
                return null;
            }
            return $consumption->productionOutput->cost_per_kg;
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
        
        return $this->contributed_weight_kg * $costPerKg;
    }
}
```

### 4. Extensión del Modelo: `ProductionOutput`

**Agregar al modelo existente** (`app/Models/ProductionOutput.php`):

```php
/**
 * Relación con las fuentes de este output
 */
public function sources()
{
    return $this->hasMany(ProductionOutputSource::class, 'production_output_id');
}

/**
 * Calcular el coste por kg de este output
 * 
 * Fórmula:
 * cost_per_kg = (suma de costes de todas las fuentes) / weight_kg
 * 
 * Si hay costes adicionales del proceso, se agregan proporcionalmente
 */
public function getCostPerKgAttribute(): ?float
{
    // 1. Calcular coste de materias primas desde fuentes
    $totalSourceCost = 0;
    $hasSourceCost = false;
    
    foreach ($this->sources as $source) {
        $sourceCost = $source->source_total_cost;
        if ($sourceCost !== null) {
            $totalSourceCost += $sourceCost;
            $hasSourceCost = true;
        }
    }
    
    if (!$hasSourceCost) {
        return null; // No hay costes de materias primas
    }
    
    // 2. Agregar costes adicionales del proceso (futuro)
    // Por ahora, solo materias primas
    $totalCost = $totalSourceCost;
    
    // 3. Calcular coste por kg
    if ($this->weight_kg <= 0) {
        return null;
    }
    
    return $totalCost / $this->weight_kg;
}

/**
 * Calcular el coste total de este output
 */
public function getTotalCostAttribute(): ?float
{
    $costPerKg = $this->cost_per_kg;
    if ($costPerKg === null) {
        return null;
    }
    
    return $this->weight_kg * $costPerKg;
}

/**
 * Obtener el desglose de costes (para análisis)
 */
public function getCostBreakdownAttribute(): array
{
    $breakdown = [
        'materials' => [
            'total_cost' => 0,
            'cost_per_kg' => 0,
            'sources' => [],
        ],
        'additional_costs' => [
            'total_cost' => 0,
            'cost_per_kg' => 0,
            'breakdown' => [],
        ],
        'total' => [
            'total_cost' => 0,
            'cost_per_kg' => 0,
        ],
    ];
    
    // Calcular costes de materias primas
    $materialsCost = 0;
    foreach ($this->sources as $source) {
        $sourceCost = $source->source_total_cost;
        if ($sourceCost !== null) {
            $materialsCost += $sourceCost;
            $breakdown['materials']['sources'][] = [
                'source_type' => $source->source_type,
                'contributed_weight_kg' => $source->contributed_weight_kg,
                'contribution_percentage' => $source->contribution_percentage,
                'source_cost_per_kg' => $source->source_cost_per_kg,
                'source_total_cost' => $sourceCost,
            ];
        }
    }
    
    $breakdown['materials']['total_cost'] = $materialsCost;
    $breakdown['materials']['cost_per_kg'] = $this->weight_kg > 0 
        ? ($materialsCost / $this->weight_kg) 
        : 0;
    
    // Calcular costes adicionales (futuro)
    // Por ahora, vacío
    
    // Total
    $totalCost = $materialsCost; // + additional costs (futuro)
    $breakdown['total']['total_cost'] = $totalCost;
    $breakdown['total']['cost_per_kg'] = $this->weight_kg > 0 
        ? ($totalCost / $this->weight_kg) 
        : 0;
    
    return $breakdown;
}
```

---

## 🔄 Algoritmo de Cálculo de Proveniencia

### Escenario 1: Output Simple (Un solo input)

**Caso**: Un proceso consume 1 caja de 30kg y produce 25kg de producto (merma de 5kg).

**Cálculo**:
1. Se crea `ProductionOutput` con `weight_kg = 25`
2. Se crea `ProductionOutputSource`:
   - `source_type = 'stock_box'`
   - `production_input_id = [ID del ProductionInput]`
   - `contributed_weight_kg = 30` (peso del input)
   - `contribution_percentage = 100%` (todo el output proviene de este input)
   - **Nota**: El `contributed_weight_kg` puede ser mayor que el `weight_kg` del output si hay merma

**Coste**:
- Coste del input: 30kg × 10€/kg = 300€
- Coste por kg del output: 300€ / 25kg = 12€/kg

### Escenario 2: Output con Múltiples Inputs

**Caso**: Un proceso consume:
- Caja 1: 30kg a 10€/kg
- Caja 2: 25kg a 12€/kg
- Output del padre: 20kg (ya tiene coste calculado de 15€/kg)

Produce: 70kg de producto (rendimiento positivo de 5kg).

**Cálculo**:
1. Se crea `ProductionOutput` con `weight_kg = 70`
2. Se crean 3 `ProductionOutputSource`:
   - Source 1: `contributed_weight_kg = 30`, `contribution_percentage = 42.86%`
   - Source 2: `contributed_weight_kg = 25`, `contribution_percentage = 35.71%`
   - Source 3: `contributed_weight_kg = 20`, `contribution_percentage = 28.57%`

**Coste**:
- Coste Source 1: 30kg × 10€/kg = 300€
- Coste Source 2: 25kg × 12€/kg = 300€
- Coste Source 3: 20kg × 15€/kg = 300€
- Coste total: 900€
- Coste por kg del output: 900€ / 70kg = 12.86€/kg

### Escenario 3: Proceso con Merma

**Caso**: Un proceso consume 100kg y produce 80kg (merma de 20kg).

**Cálculo**:
- El `contributed_weight_kg` en las fuentes será 100kg (peso consumido)
- El `weight_kg` del output será 80kg (peso producido)
- El coste se calcula sobre el peso consumido (100kg), pero se distribuye sobre el peso producido (80kg)
- **Resultado**: El coste por kg aumenta proporcionalmente a la merma

**Ejemplo**:
- Input: 100kg × 10€/kg = 1000€
- Output: 80kg
- Coste por kg: 1000€ / 80kg = 12.50€/kg (aumenta 25% por la merma)

### Escenario 4: Proceso con Rendimiento Positivo

**Caso**: Un proceso consume 100kg y produce 120kg (rendimiento positivo de 20kg, ej: envasado con salmuera).

**Cálculo**:
- El `contributed_weight_kg` en las fuentes será 100kg (peso consumido)
- El `weight_kg` del output será 120kg (peso producido)
- El coste se calcula sobre el peso consumido (100kg), pero se distribuye sobre el peso producido (120kg)
- **Resultado**: El coste por kg disminuye proporcionalmente al rendimiento

**Ejemplo**:
- Input: 100kg × 10€/kg = 1000€
- Output: 120kg
- Coste por kg: 1000€ / 120kg = 8.33€/kg (disminuye 16.67% por el rendimiento)

**⚠️ Nota importante**: El rendimiento positivo puede deberse a agregación de agua/salmuera. En ese caso, el coste por kg del producto final será menor, pero el coste total se mantiene.

---

## 📝 Lógica de Creación de Fuentes

### Al Crear un ProductionOutput

**Endpoint**: `POST /v2/production-outputs`

**Algoritmo propuesto**:

1. **Validar que el proceso tenga inputs**:
   - Si no tiene inputs (ni `ProductionInput` ni `ProductionOutputConsumption`), no se pueden crear fuentes automáticamente
   - El usuario debe indicar manualmente las fuentes o se asume que el coste es 0

2. **Obtener todos los inputs del proceso**:
   - `ProductionInput` (cajas del stock)
   - `ProductionOutputConsumption` (outputs del padre consumidos)

3. **Distribuir el peso del output entre los inputs**:
   - **Opción A - Proporcional al peso de inputs** (Automática, por defecto):
     - Si el proceso tiene inputs con pesos [30kg, 25kg, 20kg] y produce 70kg:
     - Source 1: `contributed_weight_kg = 30`, `contribution_percentage = 30/75 = 40%`
     - Source 2: `contributed_weight_kg = 25`, `contribution_percentage = 25/75 = 33.33%`
     - Source 3: `contributed_weight_kg = 20`, `contribution_percentage = 20/75 = 26.67%`
   
   - **Opción B - Manual (Especificando kg)**:
     - El usuario puede especificar `contributed_weight_kg` para cada fuente
     - El sistema calculará automáticamente el `contribution_percentage`
     - Útil cuando se conoce exactamente cuántos kg de cada input se usaron
   
   - **Opción C - Manual (Especificando porcentaje)**:
     - El usuario puede especificar `contribution_percentage` para cada fuente
     - El sistema calculará automáticamente el `contributed_weight_kg`
     - Útil cuando se conoce el porcentaje de contribución pero no el peso exacto

4. **Crear registros en `production_output_sources`**:
   - Un registro por cada input que contribuye al output

**Ejemplos de request**:

**Ejemplo 1: Especificando kg (contributed_weight_kg)**:
```json
{
  "production_record_id": 5,
  "product_id": 12,
  "lot_id": "LOT-2025-001-FIL",
  "boxes": 10,
  "weight_kg": 95.0,
  "sources": [
    {
      "source_type": "stock_box",
      "production_input_id": 10,
      "contributed_weight_kg": 30
      // contribution_percentage se calcula automáticamente: 30/95 = 31.58%
    },
    {
      "source_type": "stock_box",
      "production_input_id": 11,
      "contributed_weight_kg": 25
      // contribution_percentage se calcula automáticamente: 25/95 = 26.32%
    },
    {
      "source_type": "parent_output",
      "production_output_consumption_id": 3,
      "contributed_weight_kg": 40
      // contribution_percentage se calcula automáticamente: 40/95 = 42.10%
    }
  ]
}
```

**Ejemplo 2: Especificando porcentaje (contribution_percentage)**:
```json
{
  "production_record_id": 5,
  "product_id": 12,
  "lot_id": "LOT-2025-001-FIL",
  "boxes": 10,
  "weight_kg": 95.0,
  "sources": [
    {
      "source_type": "stock_box",
      "production_input_id": 10,
      "contribution_percentage": 31.58
      // contributed_weight_kg se calcula automáticamente: 95 × 31.58% = 30kg
    },
    {
      "source_type": "stock_box",
      "production_input_id": 11,
      "contribution_percentage": 26.32
      // contributed_weight_kg se calcula automáticamente: 95 × 26.32% = 25kg
    },
    {
      "source_type": "parent_output",
      "production_output_consumption_id": 3,
      "contribution_percentage": 42.10
      // contributed_weight_kg se calcula automáticamente: 95 × 42.10% = 40kg
    }
  ]
}
```

**Ejemplo 3: Sin especificar sources (cálculo automático proporcional)**:
```json
{
  "production_record_id": 5,
  "product_id": 12,
  "lot_id": "LOT-2025-001-FIL",
  "boxes": 10,
  "weight_kg": 95.0
  // sources se calcula automáticamente de forma proporcional al peso de inputs
}
```

**⚠️ Reglas de validación**:
- Se debe especificar **O bien** `contributed_weight_kg` **O bien** `contribution_percentage` (no ambos, no ninguno)
- Si se especifica uno, el otro se calcula automáticamente
- La suma de `contribution_percentage` debe ser ≈ 100% (con tolerancia de 0.01%)

### Al Actualizar un ProductionOutput

**Endpoint**: `PUT /v2/production-outputs/{id}`

- Si se actualiza `weight_kg`, se deben recalcular los `contribution_percentage` de las fuentes
- Si se actualiza `sources`, se reemplazan las fuentes existentes

### Al Eliminar un ProductionOutput

- Las fuentes se eliminan en cascada (onDelete: cascade)

---

## 🔮 Extensión Futura: Costes Adicionales

### Fase 2: Implementación de Costes Adicionales

Cuando se implementen los costes adicionales (producción, personal, operativos, envases), el cálculo se extenderá:

**Fórmula extendida**:

```
cost_per_kg = (
    coste_materias_primas + 
    coste_produccion_proceso +      // Costes de producción del proceso específico
    coste_produccion_lote +         // Costes de producción del lote completo (distribuidos)
    coste_personal_proceso +        // Costes de personal del proceso específico
    coste_personal_lote +            // Costes de personal del lote completo (distribuidos)
    coste_operativos_proceso +      // Costes operativos del proceso específico
    coste_operativos_lote +         // Costes operativos del lote completo (distribuidos)
    coste_envases_proceso +         // Costes de envases del proceso específico
    coste_envases_lote              // Costes de envases del lote completo (distribuidos)
) / weight_kg
```

**Distribución de costes adicionales a nivel de proceso**:

Los costes adicionales a nivel de proceso se distribuyen proporcionalmente al peso de los outputs del proceso:

```php
// En ProductionRecord
public function distributeProcessAdditionalCosts()
{
    $totalOutputWeight = $this->total_output_weight;
    if ($totalOutputWeight <= 0) {
        return;
    }
    
    $processCosts = $this->productionCosts()
        ->whereNotNull('production_record_id')
        ->whereNull('production_id')
        ->sum('total_cost');
    
    foreach ($this->outputs as $output) {
        $outputPercentage = ($output->weight_kg / $totalOutputWeight) * 100;
        $outputAdditionalCost = ($processCosts * $outputPercentage) / 100;
        
        // Agregar al coste del output
        // Esto se calculará dinámicamente en getCostPerKgAttribute()
    }
}
```

**Distribución de costes adicionales a nivel de producción (lote)**:

Los costes adicionales a nivel de producción se distribuyen proporcionalmente al peso de los outputs finales del lote:

```php
// En Production
public function distributeProductionAdditionalCosts()
{
    // Obtener solo outputs de nodos finales
    $finalOutputs = $this->getFinalNodesOutputs();
    $totalFinalOutputWeight = $finalOutputs->sum('weight_kg');
    
    if ($totalFinalOutputWeight <= 0) {
        return;
    }
    
    $productionCosts = ProductionCost::where('production_id', $this->id)
        ->whereNull('production_record_id')
        ->sum('total_cost');
    
    foreach ($finalOutputs as $output) {
        $outputPercentage = ($output->weight_kg / $totalFinalOutputWeight) * 100;
        $outputAdditionalCost = ($productionCosts * $outputPercentage) / 100;
        
        // Agregar al coste del output
        // Esto se calculará dinámicamente en getCostPerKgAttribute()
    }
}
```

**Ejemplo de costes a nivel de proceso**:
- Proceso tiene costes adicionales: 500€ (producción) + 300€ (personal) = 800€
- Output 1: 60kg (60% del total del proceso)
- Output 2: 40kg (40% del total del proceso)
- Output 1 recibe: 800€ × 60% = 480€ adicionales
- Output 2 recibe: 800€ × 40% = 320€ adicionales

**Ejemplo de costes a nivel de producción (lote)**:
- Lote completo tiene costes generales: 2000€ (producción) + 1500€ (personal) = 3500€
- Output final 1 (nodo final A): 100kg (50% del total de outputs finales)
- Output final 2 (nodo final B): 100kg (50% del total de outputs finales)
- Output final 1 recibe: 3500€ × 50% = 1750€ adicionales
- Output final 2 recibe: 3500€ × 50% = 1750€ adicionales

**⚠️ Nota importante**: Los costes a nivel de producción solo se distribuyen entre los outputs de nodos finales, ya que son los que generan stock físico.

---

## 🔍 Funcionamiento Detallado de Costes Adicionales

### 1. Cuándo y Cómo se Registran los Costes

#### 1.1 Costes a Nivel de Proceso

**Cuándo se registran**:
- Durante o después de la ejecución de un proceso específico
- Se registran cuando se conocen los costes reales de ese proceso
- Pueden registrarse en cualquier momento mientras el lote esté abierto

**Ejemplos de costes a nivel de proceso con catálogo**:
- **Producción**: 
  - Catálogo: "Mantenimiento máquina" - Coste total: 500€
  - Catálogo: "Energía eléctrica" - Coste por kg: 0.50€/kg
  - Catálogo: "Agua industrial" - Coste por kg: 0.20€/kg
- **Personal**: 
  - Catálogo: "Personal producción" (nombre personalizado: "Turno mañana") - Coste total: 300€
  - Catálogo: "Personal producción" (nombre personalizado: "Turno tarde") - Coste total: 280€
- **Operativos**: 
  - Catálogo: "Limpieza general" - Coste total: 100€
  - Ad-hoc: "Consumibles proceso" - Coste por kg: 0.15€/kg (no está en catálogo)
- **Envases**: 
  - Catálogo: "Envases plástico" - Coste por kg: 0.30€/kg
  - Ad-hoc: "Etiquetas especiales" - Coste total: 50€ (no está en catálogo)

**Cómo se registran**:
- Se crea un registro en `production_costs` con `production_record_id` y `production_id = null`
- **Opción A - Desde catálogo** (Recomendado):
  - Se selecciona un coste del catálogo (`cost_catalog_id`)
  - El `name` y `cost_type` se obtienen automáticamente del catálogo
  - El usuario puede personalizar el `name` si necesita una variación específica
- **Opción B - Coste ad-hoc**:
  - `cost_catalog_id` es null
  - Se especifica `name` y `cost_type` manualmente
- Se indica **O bien** el coste total (`total_cost`) **O bien** el coste por kg (`cost_per_kg`)

**Ejemplo práctico con catálogo de costes**:
```
Proceso: "Fileteado" (ID: 5)
- Coste de producción:
  * Catálogo: "Mantenimiento máquina" (ID: 1)
  * Tipo: production
  * Coste total: 500€
- Coste de personal:
  * Catálogo: "Personal producción" (ID: 4)
  * Nombre personalizado: "Personal fileteado - Turno mañana"
  * Tipo: labor
  * Coste total: 300€ (8 horas × 37.50€/hora)
- Coste operativo:
  * Catálogo: "Energía eléctrica" (ID: 2)
  * Tipo: operational
  * Coste por kg: 0.50€/kg (se multiplica por peso de outputs)
- Total costes del proceso: 800€ + (peso_outputs × 0.50€/kg)
```

#### 1.2 Costes a Nivel de Producción (Lote)

**Cuándo se registran**:
- Al finalizar el lote completo o durante su ejecución
- Se registran cuando hay costes generales que no se pueden asignar a un proceso específico
- Pueden registrarse en cualquier momento mientras el lote esté abierto

**Ejemplos de costes a nivel de producción con catálogo**:
- **Producción**: 
  - Catálogo: "Energía eléctrica" (nombre personalizado: "Energía eléctrica general") - Coste total: 2000€
  - Catálogo: "Agua industrial" (nombre personalizado: "Agua general instalaciones") - Coste por kg: 0.10€/kg
  - Ad-hoc: "Servicios externos" - Coste total: 500€ (no está en catálogo)
- **Personal**: 
  - Catálogo: "Supervisión" - Coste total: 1000€
  - Catálogo: "Control de calidad" - Coste total: 500€
  - Ad-hoc: "Gestión de lote" - Coste total: 300€ (no está en catálogo)
- **Operativos**: 
  - Catálogo: "Limpieza general" (nombre personalizado: "Limpieza general instalaciones") - Coste total: 400€
  - Ad-hoc: "Mantenimiento general" - Coste total: 300€ (no está en catálogo)
  - Ad-hoc: "Servicios de limpieza externos" - Coste por kg: 0.05€/kg (no está en catálogo)
- **Envases**: 
  - Ad-hoc: "Envases generales" - Coste total: 200€ (no está en catálogo)
  - Ad-hoc: "Material de embalaje" - Coste por kg: 0.08€/kg (no está en catálogo)

**Cómo se registran**:
- Se crea un registro en `production_costs` con `production_id` y `production_record_id = null`
- **Opción A - Desde catálogo** (Recomendado):
  - Se selecciona un coste del catálogo (`cost_catalog_id`)
  - El `name` y `cost_type` se obtienen automáticamente del catálogo
  - El usuario puede personalizar el `name` si necesita una variación específica
- **Opción B - Coste ad-hoc**:
  - `cost_catalog_id` es null
  - Se especifica `name` y `cost_type` manualmente
- Se indica **O bien** el coste total (`total_cost`) **O bien** el coste por kg (`cost_per_kg`)

**Ejemplo práctico con catálogo de costes**:
```
Lote: "LOT-2025-001" (ID: 10)
- Coste de producción:
  * Catálogo: "Energía eléctrica" (ID: 2)
  * Nombre personalizado: "Energía eléctrica general"
  * Tipo: production
  * Coste total: 1200€
- Coste de producción:
  * Catálogo: "Agua industrial" (ID: 3)
  * Tipo: production
  * Coste total: 800€
- Coste de personal:
  * Catálogo: "Supervisión" (ID: 7)
  * Tipo: labor
  * Coste total: 1000€
- Coste de personal:
  * Catálogo: "Control de calidad" (ID: 8)
  * Tipo: labor
  * Coste total: 500€
- Coste operativo:
  * Catálogo: "Limpieza general" (ID: 5)
  * Tipo: operational
  * Coste por kg: 0.30€/kg (se multiplica por peso de outputs finales)
- Total costes del lote: 3500€ + (peso_outputs_finales × 0.30€/kg)
```

### 2. Cómo se Distribuyen los Costes

#### 2.1 Distribución de Costes a Nivel de Proceso

**Principio**: Los costes del proceso se distribuyen proporcionalmente entre TODOS los outputs de ese proceso.

**Algoritmo**:
1. Se obtienen todos los costes adicionales del proceso
2. Para cada coste:
   - Si tiene `total_cost`: Se usa directamente
   - Si tiene `cost_per_kg`: Se multiplica por el peso total de outputs del proceso
3. Se suman todos los costes (totales + calculados desde cost_per_kg)
4. Se calcula el peso total de outputs del proceso
5. Para cada output del proceso:
   - Se calcula su porcentaje del peso total: `(output.weight_kg / total_output_weight) × 100`
   - Se asigna coste proporcional: `coste_total_proceso × porcentaje_output`

**Ejemplo detallado con costes totales y por kg**:
```
Proceso "Eviscerado" (ID: 1):
- Costes adicionales del proceso:
  * "Mantenimiento máquina" (production): total_cost = 500€
  * "Personal eviscerado" (labor): total_cost = 300€
  * "Energía eléctrica" (operational): cost_per_kg = 0.50€/kg
  * Total outputs: 80kg
  * Coste energía: 80kg × 0.50€/kg = 40€
  * Total costes: 500€ + 300€ + 40€ = 840€

- Outputs del proceso:
  * Output 1: "Atún eviscerado" - 60kg
  * Output 2: "Desperdicios" - 20kg (subproducto)
  * Total: 80kg

- Distribución:
  * Output 1: 60kg / 80kg = 75% → 840€ × 75% = 630€
  * Output 2: 20kg / 80kg = 25% → 840€ × 25% = 210€
```

**⚠️ Punto importante**: Todos los outputs del proceso reciben costes adicionales, incluso los subproductos o desperdicios. Esto permite tener coste completo de todo lo que sale del proceso.

#### 2.2 Distribución de Costes a Nivel de Producción (Lote)

**Principio**: Los costes del lote se distribuyen proporcionalmente entre SOLO los outputs de nodos finales (los que generan stock físico).

**Algoritmo**:
1. Se identifican todos los nodos finales del lote
2. Se obtienen todos los outputs de esos nodos finales
3. Se calcula el peso total de outputs finales
4. Para cada coste del lote:
   - Si tiene `total_cost`: Se usa directamente
   - Si tiene `cost_per_kg`: Se multiplica por el peso total de outputs finales
5. Se suman todos los costes (totales + calculados desde cost_per_kg)
6. Para cada output final:
   - Se calcula su porcentaje del peso total de outputs finales
   - Se asigna coste proporcional

**Ejemplo detallado con costes totales y por kg**:
```
Lote "LOT-2025-001" (ID: 10):
- Costes adicionales del lote:
  * "Energía eléctrica general" (production): total_cost = 2000€
  * "Supervisión de lote" (labor): total_cost = 1000€
  * "Control de calidad" (labor): total_cost = 500€
  * "Limpieza general" (operational): cost_per_kg = 0.30€/kg
  * Total outputs finales: 180kg
  * Coste limpieza: 180kg × 0.30€/kg = 54€
  * Total costes: 2000€ + 1000€ + 500€ + 54€ = 3554€

- Outputs finales del lote:
  * Nodo final A - "Filetes de atún": 100kg
  * Nodo final B - "Atún en conserva": 80kg
  * Total outputs finales: 180kg

- Distribución:
  * Output final A: 100kg / 180kg = 55.56% → 3554€ × 55.56% = 1974.60€
  * Output final B: 80kg / 180kg = 44.44% → 3554€ × 44.44% = 1579.40€
```

**⚠️ Punto importante**: Los costes del lote NO se distribuyen a outputs intermedios, solo a outputs finales. Esto es porque los outputs intermedios ya tienen sus propios costes de proceso, y los costes del lote representan costes generales que solo afectan al producto final.

### 3. Cálculo Completo del Coste por kg

#### 3.1 Fórmula Completa

Para cada `ProductionOutput`, el coste por kg se calcula así:

```
cost_per_kg = (
    coste_materias_primas +           // Desde sources (recursivo)
    coste_produccion_proceso +         // Costes de producción del proceso (distribuidos)
    coste_personal_proceso +           // Costes de personal del proceso (distribuidos)
    coste_operativos_proceso +         // Costes operativos del proceso (distribuidos)
    coste_envases_proceso +            // Costes de envases del proceso (distribuidos)
    coste_produccion_lote +            // Costes de producción del lote (solo outputs finales)
    coste_personal_lote +              // Costes de personal del lote (solo outputs finales)
    coste_operativos_lote +            // Costes operativos del lote (solo outputs finales)
    coste_envases_lote                 // Costes de envases del lote (solo outputs finales)
) / weight_kg
```

#### 3.2 Orden de Cálculo

El cálculo se hace en este orden para garantizar que los costes se propaguen correctamente:

1. **Calcular costes de materias primas** (desde sources, recursivamente):
   - Si la fuente es `stock_box` → coste desde recepción
   - Si la fuente es `parent_output` → coste del output del padre (que ya incluye todos sus costes)

2. **Agregar costes del proceso** (solo para outputs de ese proceso):
   - Obtener costes adicionales del proceso
   - Distribuir proporcionalmente entre outputs del proceso
   - Agregar al coste de materias primas

3. **Agregar costes del lote** (solo para outputs finales):
   - Obtener costes adicionales del lote
   - Distribuir proporcionalmente entre outputs finales
   - Agregar al coste acumulado

### 4. Flujo Completo Paso a Paso

#### Escenario Completo: Producción de Filetes de Atún

**Estructura**:
- Recepción: 100kg de "Atún entero" a 10€/kg
- Proceso 1 (Eviscerado): Consume 100kg, produce 80kg de "Atún eviscerado"
- Proceso 2 (Fileteado): Consume 60kg del proceso 1, produce 50kg de "Filetes"
- Proceso 3 (Envasado): Consume 50kg del proceso 2, produce 60kg de "Filetes envasados" (rendimiento por salmuera)

**Paso 1: Crear Output del Proceso 1 (Eviscerado)**

```
Output: "Atún eviscerado" - 80kg

1. Calcular coste de materias primas:
   - Source: 100kg de "Atún entero" a 10€/kg = 1000€
   - Coste materias primas: 1000€

2. Registrar costes del proceso 1:
   - Producción: "Mantenimiento máquina eviscerado" - 500€ (total)
   - Personal: "Personal eviscerado" - 300€ (total)
   - Operativo: "Energía eléctrica" - 0.50€/kg (por kg)
   - Si outputs = 80kg: Coste energía = 80kg × 0.50€/kg = 40€
   - Total costes proceso: 800€ + 40€ = 840€

3. Distribuir costes del proceso:
   - Output: 80kg (100% del proceso, único output)
   - Coste proceso asignado: 800€ × 100% = 800€

4. Calcular coste total:
   - Coste materias primas: 1000€
   - Coste proceso: 800€
   - Total: 1800€
   - Coste por kg: 1800€ / 80kg = 22.50€/kg
```

**Paso 2: Crear Output del Proceso 2 (Fileteado)**

```
Output: "Filetes" - 50kg

1. Calcular coste de materias primas:
   - Source: 60kg de "Atún eviscerado" a 22.50€/kg = 1350€
   - Coste materias primas: 1350€

2. Registrar costes del proceso 2:
   - Producción: 400€ (maquinaria de fileteado)
   - Personal: 250€ (personal del proceso)
   - Total costes proceso: 650€

3. Distribuir costes del proceso:
   - Output: 50kg (100% del proceso, único output)
   - Coste proceso asignado: 650€ × 100% = 650€

4. Calcular coste total:
   - Coste materias primas: 1350€
   - Coste proceso: 650€
   - Total: 2000€
   - Coste por kg: 2000€ / 50kg = 40.00€/kg
```

**Paso 3: Crear Output del Proceso 3 (Envasado) - Nodo Final**

```
Output: "Filetes envasados" - 60kg (NODO FINAL)

1. Calcular coste de materias primas:
   - Source: 50kg de "Filetes" a 40.00€/kg = 2000€
   - Coste materias primas: 2000€

2. Registrar costes del proceso 3:
   - Producción: 300€ (maquinaria de envasado)
   - Personal: 200€ (personal del proceso)
   - Envases: 150€ (envases específicos del proceso)
   - Total costes proceso: 650€

3. Distribuir costes del proceso:
   - Output: 60kg (100% del proceso, único output)
   - Coste proceso asignado: 650€ × 100% = 650€

4. Registrar costes del lote (solo para outputs finales):
   - Producción: "Energía eléctrica general" - 2000€ (total)
   - Personal: "Supervisión de lote" - 1500€ (total)
   - Operativo: "Limpieza general" - 0.30€/kg (por kg)
   - Si outputs finales = 60kg: Coste limpieza = 60kg × 0.30€/kg = 18€
   - Total costes lote: 3500€ + 18€ = 3518€

5. Distribuir costes del lote:
   - Output final: 60kg (100% de outputs finales, único output final)
   - Coste lote asignado: 3500€ × 100% = 3500€

6. Calcular coste total:
   - Coste materias primas: 2000€
   - Coste proceso: 650€
   - Coste lote: 3518€
   - Total: 6168€
   - Coste por kg: 6168€ / 60kg = 102.80€/kg
```

### 5. Casos Especiales y Consideraciones

#### 5.1 Proceso con Múltiples Outputs

**Caso**: Un proceso produce dos outputs diferentes (ej: filetes y desperdicios).

**Distribución de costes**:
- Los costes del proceso se distribuyen proporcionalmente al peso de cada output
- Cada output tiene su propio coste por kg calculado independientemente
- Los outputs pueden tener costes muy diferentes si tienen pesos muy diferentes

**Ejemplo**:
```
Proceso "Fileteado":
- Costes del proceso: 800€
- Output 1: "Filetes" - 50kg
- Output 2: "Desperdicios" - 10kg
- Total: 60kg

Distribución:
- Output 1: 50kg / 60kg = 83.33% → 800€ × 83.33% = 666.64€
- Output 2: 10kg / 60kg = 16.67% → 800€ × 16.67% = 133.36€
```

#### 5.2 Lote con Múltiples Outputs Finales

**Caso**: Un lote produce múltiples productos finales diferentes.

**Distribución de costes del lote**:
- Los costes del lote se distribuyen proporcionalmente al peso de cada output final
- Cada output final recibe su parte proporcional de los costes del lote
- Los outputs finales pueden tener costes muy diferentes si tienen pesos muy diferentes

**Ejemplo**:
```
Lote "LOT-2025-001":
- Costes del lote: 5000€
- Output final 1: "Filetes" - 100kg
- Output final 2: "Atún en conserva" - 50kg
- Total outputs finales: 150kg

Distribución:
- Output final 1: 100kg / 150kg = 66.67% → 5000€ × 66.67% = 3333.50€
- Output final 2: 50kg / 150kg = 33.33% → 5000€ × 33.33% = 1666.50€
```

#### 5.3 Output Intermedio vs Output Final

**Diferencia clave**:
- **Output intermedio**: Solo recibe costes de materias primas + costes de su proceso
- **Output final**: Recibe costes de materias primas + costes de su proceso + costes del lote

**Ejemplo**:
```
Output intermedio "Atún eviscerado" (proceso 1):
- Coste materias primas: 1000€
- Coste proceso 1: 800€
- Coste lote: 0€ (no es final)
- Total: 1800€
- Coste por kg: 22.50€/kg

Output final "Filetes envasados" (proceso 3):
- Coste materias primas: 2000€
- Coste proceso 3: 650€
- Coste lote: 3500€ (es final)
- Total: 6150€
- Coste por kg: 102.50€/kg
```

#### 5.4 Actualización de Costes

**Escenarios**:
1. **Agregar coste después de crear output**:
   - Si se agrega un coste del proceso después de crear outputs, se deben recalcular los costes de todos los outputs de ese proceso
   - Si se agrega un coste del lote, se deben recalcular los costes de todos los outputs finales

2. **Modificar coste existente**:
   - Se recalcula automáticamente la distribución
   - Se actualizan los costes de los outputs afectados

3. **Eliminar coste**:
   - Se recalcula la distribución sin ese coste
   - Se actualizan los costes de los outputs afectados

### 6. Desglose de Costes (Cost Breakdown)

Para cada output, se puede obtener un desglose completo de costes:

**Estructura del desglose (con nombres variables)**:
```
{
  "materials": {
    "total_cost": 2000.00,
    "cost_per_kg": 33.33,
    "sources": [
      {
        "source_type": "parent_output",
        "contributed_weight_kg": 50,
        "contribution_percentage": 83.33,
        "source_cost_per_kg": 40.00,
        "source_total_cost": 2000.00
      }
    ]
  },
  "process_costs": {
    "production": {
      "total_cost": 300.00,
      "cost_per_kg": 5.00,
      "breakdown": [
        {
          "name": "Mantenimiento máquina envasado",
          "total_cost": 300.00,
          "cost_per_kg": 5.00
        }
      ]
    },
    "labor": {
      "total_cost": 200.00,
      "cost_per_kg": 3.33,
      "breakdown": [
        {
          "name": "Personal envasado",
          "total_cost": 200.00,
          "cost_per_kg": 3.33
        }
      ]
    },
    "operational": {
      "total_cost": 0.00,
      "cost_per_kg": 0.00,
      "breakdown": []
    },
    "packaging": {
      "total_cost": 150.00,
      "cost_per_kg": 2.50,
      "breakdown": [
        {
          "name": "Envases plástico",
          "total_cost": 150.00,
          "cost_per_kg": 2.50
        }
      ]
    },
    "total": {
      "total_cost": 650.00,
      "cost_per_kg": 10.83
    }
  },
  "production_costs": {
    "production": {
      "total_cost": 2000.00,
      "cost_per_kg": 33.33,
      "breakdown": [
        {
          "name": "Energía eléctrica general",
          "total_cost": 2000.00,
          "cost_per_kg": 33.33
        }
      ]
    },
    "labor": {
      "total_cost": 1500.00,
      "cost_per_kg": 25.00,
      "breakdown": [
        {
          "name": "Supervisión de lote",
          "total_cost": 1000.00,
          "cost_per_kg": 16.67
        },
        {
          "name": "Control de calidad",
          "total_cost": 500.00,
          "cost_per_kg": 8.33
        }
      ]
    },
    "operational": {
      "total_cost": 18.00,
      "cost_per_kg": 0.30,
      "breakdown": [
        {
          "name": "Limpieza general",
          "total_cost": 18.00,
          "cost_per_kg": 0.30
        }
      ]
    },
    "packaging": {
      "total_cost": 0.00,
      "cost_per_kg": 0.00,
      "breakdown": []
    },
    "total": {
      "total_cost": 3518.00,
      "cost_per_kg": 58.63
    }
  },
  "total": {
    "total_cost": 6168.00,
    "cost_per_kg": 102.80
  }
}
```

### 7. Ventajas de esta Estructura

1. **Trazabilidad completa**: Se puede rastrear cada euro de coste hasta su origen
2. **Flexibilidad**: Permite costes a nivel de proceso y a nivel de lote
3. **Precisión**: Los costes se distribuyen proporcionalmente según el peso real
4. **Extensibilidad**: Fácil agregar nuevos tipos de costes en el futuro
5. **Análisis detallado**: Permite ver desglose completo de costes por tipo
6. **Recursividad**: Los costes se propagan correctamente a través de procesos hijos

### 8. Especificación de Costes: Total vs Por kg

#### 8.1 Coste Total (`total_cost`)

**Cuándo usar**:
- Cuando se conoce el coste total del proceso o producción
- Ejemplos: "Mantenimiento máquina: 500€", "Personal turno: 300€", "Servicio externo: 200€"

**Cómo funciona**:
- Se especifica el coste total directamente
- El sistema lo distribuye proporcionalmente al peso de outputs
- No depende del peso producido

**Ejemplo**:
```
Coste: "Mantenimiento máquina fileteado"
- total_cost: 500€
- cost_per_kg: null
- El proceso produce 50kg → Se distribuyen 500€ entre los outputs
- El proceso produce 100kg → Se distribuyen los mismos 500€ entre los outputs
```

#### 8.2 Coste Por kg (`cost_per_kg`)

**Cuándo usar**:
- Cuando el coste depende del peso producido
- Ejemplos: "Energía eléctrica: 0.50€/kg", "Agua: 0.20€/kg", "Limpieza: 0.30€/kg"

**Cómo funciona**:
1. Se especifica el coste por kg
2. El sistema multiplica por el peso total de outputs del proceso/producción
3. El resultado se distribuye proporcionalmente entre los outputs

**Ejemplo**:
```
Coste: "Energía eléctrica"
- total_cost: null
- cost_per_kg: 0.50€/kg
- El proceso produce 50kg → Coste total = 50kg × 0.50€/kg = 25€
- El proceso produce 100kg → Coste total = 100kg × 0.50€/kg = 50€
```

#### 8.3 Ejemplo Combinado

**Proceso con múltiples costes**:
```
Proceso "Fileteado":
1. Coste total:
   - Nombre: "Mantenimiento máquina"
   - Tipo: production
   - total_cost: 500€
   - cost_per_kg: null

2. Coste por kg:
   - Nombre: "Energía eléctrica"
   - Tipo: operational
   - total_cost: null
   - cost_per_kg: 0.50€/kg

3. Si el proceso produce 80kg:
   - Coste mantenimiento: 500€ (fijo)
   - Coste energía: 80kg × 0.50€/kg = 40€ (variable)
   - Total: 540€
```

### 9. Consideraciones de Implementación

1. **Orden de cálculo**: Los costes deben calcularse en orden (materias primas → proceso → lote)
2. **Cálculo de costes por kg**: 
   - Primero se calcula el coste total desde `cost_per_kg` multiplicando por peso
   - Luego se distribuye proporcionalmente igual que los costes totales
3. **Cacheo**: Los costes calculados pueden cachearse para mejorar rendimiento
4. **Recálculo**: Cuando se modifican costes o pesos, se deben recalcular todos los outputs afectados
5. **Validaciones**: 
   - Verificar que se especifique O bien `total_cost` O bien `cost_per_kg`
   - Verificar que los costes se distribuyan correctamente (suma = 100%)
6. **Historial**: Mantener historial de cambios en costes para auditoría
7. **Nombres variables**: Permitir que el usuario especifique cualquier nombre para identificar costes específicos

### 10. Ejemplos Prácticos de Registro de Costes

#### 10.1 Registrar Coste Total a Nivel de Proceso

**Endpoint**: `POST /v2/production-costs`

**Request - Coste total desde catálogo (Recomendado)**:
```json
{
  "production_record_id": 5,
  "production_id": null,
  "cost_catalog_id": 1,
  "name": null,  // Se obtiene del catálogo automáticamente
  "cost_type": null,  // Se obtiene del catálogo automáticamente
  "description": "Mantenimiento preventivo mensual de la máquina",
  "total_cost": 500.00,
  "cost_per_kg": null,
  "cost_date": "2025-01-15"
}
```

**Request - Coste total desde catálogo con nombre personalizado**:
```json
{
  "production_record_id": 5,
  "production_id": null,
  "cost_catalog_id": 1,
  "name": "Mantenimiento máquina fileteado - Especial",  // Sobrescrito
  "cost_type": null,  // Se obtiene del catálogo
  "description": "Mantenimiento preventivo mensual de la máquina",
  "total_cost": 500.00,
  "cost_per_kg": null,
  "cost_date": "2025-01-15"
}
```

**Request - Coste total ad-hoc (no está en catálogo)**:
```json
{
  "production_record_id": 5,
  "production_id": null,
  "cost_catalog_id": null,
  "name": "Servicio especial de limpieza",
  "cost_type": "operational",
  "description": "Limpieza especial por inspección",
  "total_cost": 200.00,
  "cost_per_kg": null,
  "cost_date": "2025-01-15"
}
```

**Cómo funciona**:
- El coste total de 500€ se distribuirá proporcionalmente entre todos los outputs del proceso
- No depende del peso producido (es un coste fijo del proceso)

#### 10.2 Registrar Coste Por kg a Nivel de Proceso

**Request - Coste por kg desde catálogo (Recomendado)**:
```json
{
  "production_record_id": 5,
  "production_id": null,
  "cost_catalog_id": 2,  // "Energía eléctrica" del catálogo
  "name": null,  // Se obtiene del catálogo: "Energía eléctrica"
  "cost_type": null,  // Se obtiene del catálogo: "operational"
  "description": "Consumo eléctrico del proceso de fileteado",
  "total_cost": null,
  "cost_per_kg": 0.50,
  "cost_date": "2025-01-15"
}
```

**Request - Coste por kg ad-hoc**:
```json
{
  "production_record_id": 5,
  "production_id": null,
  "cost_catalog_id": null,
  "name": "Consumibles especiales",
  "cost_type": "operational",
  "description": "Consumibles específicos del proceso",
  "total_cost": null,
  "cost_per_kg": 0.15,
  "cost_date": "2025-01-15"
}
```

**Cómo funciona**:
- Si el proceso produce 80kg: Coste total = 80kg × 0.50€/kg = 40€
- Si el proceso produce 100kg: Coste total = 100kg × 0.50€/kg = 50€
- El coste se calcula multiplicando por el peso total de outputs, luego se distribuye proporcionalmente

#### 10.3 Registrar Coste Total a Nivel de Producción (Lote)

**Request - Coste total del lote desde catálogo (Recomendado)**:
```json
{
  "production_record_id": null,
  "production_id": 10,
  "cost_catalog_id": 7,  // "Supervisión" del catálogo
  "name": null,  // Se obtiene del catálogo: "Supervisión"
  "cost_type": null,  // Se obtiene del catálogo: "labor"
  "description": "Personal de supervisión dedicado al lote completo",
  "total_cost": 1500.00,
  "cost_per_kg": null,
  "cost_date": "2025-01-15"
}
```

**Request - Coste total del lote ad-hoc**:
```json
{
  "production_record_id": null,
  "production_id": 10,
  "cost_catalog_id": null,
  "name": "Gestión de lote",
  "cost_type": "labor",
  "description": "Personal de gestión dedicado al lote completo",
  "total_cost": 300.00,
  "cost_per_kg": null,
  "cost_date": "2025-01-15"
}
```

**Cómo funciona**:
- El coste total de 1500€ se distribuirá proporcionalmente entre todos los outputs finales del lote
- Solo afecta a outputs de nodos finales (los que generan stock físico)

#### 10.4 Registrar Coste Por kg a Nivel de Producción (Lote)

**Request - Coste por kg del lote desde catálogo (Recomendado)**:
```json
{
  "production_record_id": null,
  "production_id": 10,
  "cost_catalog_id": 5,  // "Limpieza general" del catálogo
  "name": null,  // Se obtiene del catálogo: "Limpieza general"
  "cost_type": null,  // Se obtiene del catálogo: "operational"
  "description": "Servicio de limpieza general de instalaciones",
  "total_cost": null,
  "cost_per_kg": 0.30,
  "cost_date": "2025-01-15"
}
```

**Request - Coste por kg del lote ad-hoc**:
```json
{
  "production_record_id": null,
  "production_id": 10,
  "cost_catalog_id": null,
  "name": "Material de embalaje",
  "cost_type": "packaging",
  "description": "Material de embalaje general del lote",
  "total_cost": null,
  "cost_per_kg": 0.08,
  "cost_date": "2025-01-15"
}
```

**Cómo funciona**:
- Si los outputs finales suman 180kg: Coste total = 180kg × 0.30€/kg = 54€
- El coste se calcula multiplicando por el peso total de outputs finales, luego se distribuye proporcionalmente

#### 10.5 Múltiples Costes del Mismo Tipo

**Ejemplo**: Un proceso puede tener múltiples costes del mismo tipo, algunos del catálogo y otros ad-hoc:

```json
// Coste 1 - Desde catálogo
{
  "production_record_id": 5,
  "cost_catalog_id": 1,  // "Mantenimiento máquina"
  "cost_type": "production",  // Se obtiene del catálogo
  "name": "Mantenimiento máquina",  // Se obtiene del catálogo
  "total_cost": 500.00,
  "cost_per_kg": null
}

// Coste 2 - Desde catálogo
{
  "production_record_id": 5,
  "cost_catalog_id": 2,  // "Energía eléctrica"
  "cost_type": "operational",  // Se obtiene del catálogo
  "name": "Energía eléctrica",  // Se obtiene del catálogo
  "total_cost": null,
  "cost_per_kg": 0.50
}

// Coste 3 - Desde catálogo
{
  "production_record_id": 5,
  "cost_catalog_id": 3,  // "Agua industrial"
  "cost_type": "operational",  // Se obtiene del catálogo
  "name": "Agua industrial",  // Se obtiene del catálogo
  "total_cost": null,
  "cost_per_kg": 0.20
}

// Coste 4 - Ad-hoc (no está en catálogo)
{
  "production_record_id": 5,
  "cost_catalog_id": null,
  "cost_type": "operational",
  "name": "Consumibles especiales",
  "total_cost": null,
  "cost_per_kg": 0.15
}
```

**Resultado**: Todos estos costes se suman y se distribuyen entre los outputs del proceso. Los costes del catálogo mantienen consistencia, mientras que los ad-hoc permiten flexibilidad.

### 11. Gestión del Catálogo de Costes

#### 11.1 Endpoints para el Catálogo

**Listar costes del catálogo**:
```
GET /v2/cost-catalog
```

**Filtrar por tipo**:
```
GET /v2/cost-catalog?cost_type=operational
```

**Crear nuevo coste en el catálogo**:
```
POST /v2/cost-catalog
{
  "name": "Nuevo coste",
  "cost_type": "operational",
  "description": "Descripción del coste",
  "default_unit": "per_kg",
  "is_active": true
}
```

**Actualizar coste del catálogo**:
```
PUT /v2/cost-catalog/{id}
```

**Desactivar coste** (sin eliminar):
```
PUT /v2/cost-catalog/{id}
{
  "is_active": false
}
```

#### 11.2 Flujo de Uso del Catálogo

**Escenario 1: Coste común (está en catálogo)**:
1. Usuario abre formulario para agregar coste
2. Sistema muestra lista desplegable con costes del catálogo
3. Usuario selecciona "Energía eléctrica" del catálogo
4. Sistema autocompleta `name` y `cost_type`
5. Usuario especifica `cost_per_kg = 0.50`
6. Se crea el registro con `cost_catalog_id = 2`

**Escenario 2: Coste con variación (está en catálogo pero necesita personalización)**:
1. Usuario selecciona "Energía eléctrica" del catálogo
2. Sistema autocompleta `name = "Energía eléctrica"`
3. Usuario modifica el nombre a "Energía eléctrica - Proceso fileteado"
4. Se crea el registro con `cost_catalog_id = 2` pero `name` personalizado

**Escenario 3: Coste especial (no está en catálogo)**:
1. Usuario busca en el catálogo y no encuentra el coste
2. Usuario puede:
   - **Opción A**: Agregar al catálogo primero (si tiene permisos)
   - **Opción B**: Crear coste ad-hoc directamente (`cost_catalog_id = null`)
3. Si crea ad-hoc, el sistema puede sugerir agregarlo al catálogo para futuros usos

#### 11.3 Ventajas del Catálogo

1. **Consistencia**: Todos usan los mismos nombres para costes comunes
2. **Análisis**: Fácil agrupar y comparar costes del mismo tipo
3. **Rapidez**: Selección rápida en lugar de escribir
4. **Sugerencias**: El sistema puede sugerir costes similares
5. **Historial**: Se puede ver qué costes del catálogo se usan más
6. **Flexibilidad**: Permite costes ad-hoc para casos especiales

### 12. Resumen: Funcionamiento de Costes con Catálogo

**Características clave**:

1. **Catálogo de costes comunes**: Tabla `cost_catalog` con costes predefinidos
   - Evita inconsistencias en nombres
   - Facilita análisis y comparaciones
   - Permite desactivar costes sin eliminar

2. **Uso del catálogo**: 
   - **Recomendado**: Seleccionar coste del catálogo (`cost_catalog_id`)
   - El `name` y `cost_type` se obtienen automáticamente
   - Se puede personalizar el `name` si es necesario

3. **Costes ad-hoc**: 
   - Permite crear costes con `cost_catalog_id = null`
   - Útil para costes especiales no catalogados
   - El sistema puede sugerir agregarlos al catálogo

4. **Especificación de coste**: Se puede especificar de dos formas:
   - **Coste total** (`total_cost`): Coste fijo que se distribuye proporcionalmente
   - **Coste por kg** (`cost_per_kg`): Coste variable que se multiplica por el peso y luego se distribuye

5. **Nivel de coste**: Puede estar a nivel de proceso o a nivel de producción (lote)

6. **Distribución**:
   - Costes de proceso → Se distribuyen entre outputs del proceso
   - Costes de producción → Se distribuyen entre outputs finales del lote

**Ventajas**:
- ✅ Consistencia en nombres de costes comunes
- ✅ Facilita análisis y comparaciones
- ✅ Rapidez al seleccionar costes frecuentes
- ✅ Flexibilidad para costes especiales (ad-hoc)
- ✅ Permite costes fijos y variables
- ✅ Trazabilidad completa de cada coste individual
- ✅ Fácil análisis por tipo de coste o por coste específico del catálogo

---

## 📊 Estructura de Respuesta API

### ProductionOutputResource Extendido

**Archivo**: `app/Http/Resources/v2/ProductionOutputResource.php`

```php
public function toArray($request)
{
    return [
        'id' => $this->id,
        'productionRecordId' => $this->production_record_id,
        'productId' => $this->product_id,
        'product' => new ProductResource($this->product),
        'lotId' => $this->lot_id,
        'boxes' => $this->boxes,
        'weightKg' => (float) $this->weight_kg,
        'averageWeightPerBox' => (float) $this->average_weight_per_box,
        
        // ✨ NUEVOS CAMPOS DE COSTE
        'costPerKg' => $this->cost_per_kg,
        'totalCost' => $this->total_cost,
        'costBreakdown' => $this->cost_breakdown,
        'sources' => ProductionOutputSourceResource::collection($this->sources),
        
        'createdAt' => $this->created_at?->toIso8601String(),
        'updatedAt' => $this->updated_at?->toIso8601String(),
    ];
}
```

### ProductionOutputSourceResource

**Archivo**: `app/Http/Resources/v2/ProductionOutputSourceResource.php`

```php
public function toArray($request)
{
    return [
        'id' => $this->id,
        'productionOutputId' => $this->production_output_id,
        'sourceType' => $this->source_type,
        'productionInputId' => $this->production_input_id,
        'productionInput' => $this->productionInput 
            ? new ProductionInputResource($this->productionInput) 
            : null,
        'productionOutputConsumptionId' => $this->production_output_consumption_id,
        'productionOutputConsumption' => $this->productionOutputConsumption 
            ? new ProductionOutputConsumptionResource($this->productionOutputConsumption) 
            : null,
        'contributedWeightKg' => (float) $this->contributed_weight_kg,
        'contributedBoxes' => $this->contributed_boxes,
        'contributionPercentage' => (float) $this->contribution_percentage,
        'sourceCostPerKg' => $this->source_cost_per_kg,
        'sourceTotalCost' => $this->source_total_cost,
        'createdAt' => $this->created_at?->toIso8601String(),
        'updatedAt' => $this->updated_at?->toIso8601String(),
    ];
}
```

---

## 🔄 Flujo de Trabajo Completo

### Ejemplo: Producción Completa con Trazabilidad

**Escenario**:
1. Recepción: 100kg de "Atún entero" a 10€/kg → Coste: 1000€
2. Proceso 1 (Eviscerado): Consume 100kg, produce 80kg de "Atún eviscerado" (merma 20kg)
3. Proceso 2 (Fileteado): Consume 60kg del proceso 1, produce 50kg de "Filetes" (merma 10kg)

**Implementación**:

#### Paso 1: Crear Output del Proceso 1

```json
POST /v2/production-outputs
{
  "production_record_id": 1,
  "product_id": 11,
  "lot_id": "LOT-2025-001-EV",
  "boxes": 8,
  "weight_kg": 80.0
}
```

**Sistema automáticamente crea**:
- `ProductionOutputSource`:
  - `source_type = 'stock_box'`
  - `production_input_id = [ID del input de 100kg]`
  - `contributed_weight_kg = 100`
  - `contribution_percentage = 100%`

**Cálculo de coste**:
- Coste del input: 100kg × 10€/kg = 1000€
- Coste por kg del output: 1000€ / 80kg = **12.50€/kg**

#### Paso 2: Crear Consumo del Proceso 2

```json
POST /v2/production-output-consumptions
{
  "production_record_id": 2,
  "production_output_id": [ID del output del proceso 1],
  "consumed_weight_kg": 60.0,
  "consumed_boxes": 5
}
```

#### Paso 3: Crear Output del Proceso 2

```json
POST /v2/production-outputs
{
  "production_record_id": 2,
  "product_id": 12,
  "lot_id": "LOT-2025-001-FIL",
  "boxes": 10,
  "weight_kg": 50.0
}
```

**Sistema automáticamente crea**:
- `ProductionOutputSource`:
  - `source_type = 'parent_output'`
  - `production_output_consumption_id = [ID del consumo]`
  - `contributed_weight_kg = 60`
  - `contribution_percentage = 100%`

**Cálculo de coste**:
- Coste del input (desde proceso 1): 60kg × 12.50€/kg = 750€
- Coste por kg del output: 750€ / 50kg = **15.00€/kg**

**Trazabilidad completa**:
- Filetes (50kg) provienen de:
  - 60kg de Atún eviscerado (que proviene de 100kg de Atún entero)
  - Coste total: 750€
  - Coste por kg: 15€/kg

---

## ⚠️ Consideraciones y Validaciones

### Validaciones

1. **Especificación de peso o porcentaje**:
   - Se debe especificar **O bien** `contributed_weight_kg` **O bien** `contribution_percentage` (no ambos, no ninguno)
   - Si se especifica uno, el otro se calcula automáticamente

2. **Suma de contribution_percentage**:
   - La suma de todos los `contribution_percentage` de las fuentes de un output debe ser ≈ 100% (con tolerancia de 0.01% por redondeo)

3. **Suma de contributed_weight_kg**:
   - La suma de `contributed_weight_kg` puede ser mayor, igual o menor que `weight_kg` del output:
     - **Mayor**: Indica merma (se consumió más de lo producido)
     - **Igual**: Sin merma ni rendimiento
     - **Menor**: Indica rendimiento positivo (se produjo más de lo consumido)

4. **Consistencia de source_type**:
   - Si `source_type = 'stock_box'`, `production_input_id` debe estar presente y `production_output_consumption_id` debe ser null
   - Si `source_type = 'parent_output'`, `production_output_consumption_id` debe estar presente y `production_input_id` debe ser null

5. **Validación de inputs existentes**:
   - No se pueden crear fuentes para inputs que no existen
   - No se pueden crear fuentes para consumos que no existen

6. **Validación de costes a nivel de producción**:
   - Los costes a nivel de producción (`production_id` presente, `production_record_id` null) solo se pueden crear si el lote tiene outputs finales
   - Los costes a nivel de proceso (`production_record_id` presente, `production_id` null) solo se pueden crear si el proceso tiene outputs

### Casos Especiales

1. **Output sin inputs**:
   - Si un proceso no tiene inputs (caso raro), el output no tendrá coste (null)
   - El usuario puede indicar manualmente el coste si es necesario

2. **Output con inputs sin coste**:
   - Si los inputs no tienen coste (cajas sin recepción), el output tampoco tendrá coste
   - Se puede indicar manualmente el coste del output

3. **Proceso con múltiples outputs**:
   - Cada output tiene sus propias fuentes
   - Los costes se calculan independientemente para cada output

4. **Actualización de inputs después de crear outputs**:
   - Si se agregan inputs después de crear outputs, se deben recalcular las fuentes
   - O se debe permitir actualizar manualmente las fuentes

---

## 📅 Plan de Implementación

### Fase 1: Estructura Base (Semana 1-2)

- [ ] Crear migración `cost_catalog`
- [ ] Crear modelo `CostCatalog`
- [ ] Crear migración `production_output_sources`
- [ ] Crear modelo `ProductionOutputSource`
- [ ] Agregar relación `sources()` en `ProductionOutput`
- [ ] Implementar accessors de coste en `ProductionOutput`
- [ ] Crear `ProductionOutputSourceResource`
- [ ] Endpoints básicos para `CostCatalog` (listar, crear)

### Fase 2: Lógica de Cálculo Automático (Semana 3)

- [ ] Implementar algoritmo de distribución proporcional de fuentes
- [ ] Actualizar `ProductionOutputService` para crear fuentes automáticamente
- [ ] Implementar recálculo de costes al actualizar outputs
- [ ] Validaciones de integridad de fuentes

### Fase 3: API y Endpoints (Semana 4)

- [ ] Actualizar `StoreProductionOutputRequest` para aceptar `sources` opcionales
- [ ] Actualizar `ProductionOutputController` para manejar fuentes
- [ ] Actualizar `ProductionOutputResource` con campos de coste
- [ ] Endpoint para obtener desglose de costes: `GET /v2/production-outputs/{id}/cost-breakdown`

### Fase 4: Testing y Validación (Semana 5)

- [ ] Tests unitarios para cálculo de costes
- [ ] Tests de integración para flujo completo
- [ ] Tests de casos especiales (merma, rendimiento, múltiples inputs)
- [ ] Validación de rendimiento con producciones grandes

### Fase 5: Documentación (Semana 6)

- [ ] Actualizar documentación de API v2
- [ ] Documentar nuevos campos y endpoints
- [ ] Ejemplos de uso
- [ ] Guía de migración para datos existentes

### Fase 6: Costes Adicionales (Futuro)

- [ ] Crear migración `production_costs` (con referencia a `cost_catalog`)
- [ ] Implementar modelo `ProductionCost` (con relación a `CostCatalog`)
- [ ] Extender cálculo de costes para incluir costes adicionales a nivel de proceso
- [ ] Extender cálculo de costes para incluir costes adicionales a nivel de producción (lote)
- [ ] API para gestionar costes adicionales (proceso y producción)
- [ ] Validaciones de distribución de costes
- [ ] Lógica de autocompletado desde catálogo
- [ ] Sugerencias para agregar costes ad-hoc al catálogo

---

## 🔗 Referencias

- [Documentación de Producciones v2](./10-Produccion-General.md)
- [Documentación de Recepciones y Costes](../26-recepciones-despachos/62-Plan-Implementacion-Recepciones-Palets-Costes.md)
- Modelos: `app/Models/ProductionOutput.php`, `app/Models/ProductionInput.php`, `app/Models/ProductionOutputConsumption.php`
- Controladores: `app/Http/Controllers/v2/ProductionOutputController.php`

---

## ✅ Checklist de Implementación

### Base de Datos
- [ ] Migración `cost_catalog` (catálogo de costes)
- [ ] Migración `production_output_sources`
- [ ] Migración `production_costs` (con referencia a `cost_catalog`)
- [ ] Índices y foreign keys
- [ ] Validaciones de constraints

### Modelos
- [ ] Modelo `CostCatalog`
- [ ] Modelo `ProductionOutputSource`
- [ ] Modelo `ProductionCost` (con relación a `CostCatalog`)
- [ ] Relación `ProductionOutput::sources()`
- [ ] Accessors de coste en `ProductionOutput`
- [ ] Método `getCostBreakdownAttribute()`

### Servicios
- [ ] Algoritmo de distribución proporcional
- [ ] Creación automática de fuentes
- [ ] Recálculo de costes

### API
- [ ] Endpoints para `CostCatalog` (CRUD)
- [ ] Actualizar `StoreProductionOutputRequest`
- [ ] Actualizar `ProductionOutputController`
- [ ] Actualizar `ProductionOutputResource`
- [ ] Crear `ProductionOutputSourceResource`
- [ ] Endpoints para `ProductionCost` (CRUD)
- [ ] Endpoint `GET /v2/production-outputs/{id}/cost-breakdown`

### Testing
- [ ] Tests de cálculo de costes
- [ ] Tests de creación de fuentes
- [ ] Tests de casos especiales
- [ ] Tests de integración

### Documentación
- [ ] Actualizar documentación de API
- [ ] Ejemplos de uso
- [ ] Guía de migración

---

---

## 📌 Resumen de Cambios Clave

### 1. Tres Tipos de Costes

El sistema manejará tres tipos de costes distintos:

1. **Costes de Recepciones** (Ya implementado):
   - Productos que provienen directamente de recepciones
   - Se calculan desde `raw_material_reception_products.price`

2. **Costes de Productos Intermedios** (A implementar):
   - Productos resultantes de procesos intermedios que NO llegan a registrarse como palets/cajas
   - Son `ProductionOutput` consumidos por procesos hijos

3. **Costes de Productos Finales** (A implementar):
   - Productos resultantes finales que SÍ se registran como palets/cajas
   - Son `ProductionOutput` de nodos finales que generan stock físico

### 2. Flexibilidad en Especificación de Fuentes

- Se puede especificar **O bien** `contributed_weight_kg` **O bien** `contribution_percentage`
- Si se especifica uno, el otro se calcula automáticamente
- Permite mayor flexibilidad según el caso de uso

### 3. Costes Adicionales a Nivel General

- Los costes adicionales pueden estar a **nivel de proceso** (`production_record_id`)
- Los costes adicionales pueden estar a **nivel de producción** (`production_id`) - lote completo
- Los costes a nivel de producción se distribuyen entre los outputs finales del lote

---

**Última actualización**: 2025-01-XX  
**Estado**: Propuesta de diseño - Pendiente de aprobación

