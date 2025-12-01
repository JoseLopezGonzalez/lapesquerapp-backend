# Análisis: Consumo de Salidas del Padre en Procesos Hijos

## 📋 Problema Identificado

### Situación Actual

El sistema actualmente solo permite que los procesos consuman inputs desde **cajas físicas del stock** (palets). Sin embargo, en la realidad del negocio, los procesos hijos deben poder consumir:

1. ✅ **Cajas del stock** (ya implementado)
2. ❌ **Salidas del proceso padre** (NO implementado)

### Ejemplo Real del Problema

```
┌─────────────────────────────┐
│ Proceso: "Fileteado"        │
│ (Proceso Padre)             │
├─────────────────────────────┤
│ Input: 500kg de pescado     │
│   (desde cajas del stock)   │
│                             │
│ Output: 300kg de filetes    │
│   (ProductionOutput)        │
└─────────────────────────────┘
           │
           │ Debería poder consumir
           │ parte de esos 300kg
           ▼
┌─────────────────────────────┐
│ Proceso: "Envasado"         │
│ (Proceso Hijo)              │
├─────────────────────────────┤
│ Input Actual:               │
│   - Solo puede consumir     │
│     cajas del stock         │
│                             │
│ Input Necesario:            │
│   - 150kg de filetes del    │
│     proceso padre           │ ❌ NO FUNCIONA
│   - 50kg desde stock        │ ✅ FUNCIONA
└─────────────────────────────┘
```

## 🔍 Análisis Técnico

### Estructura Actual

#### ProductionInput
```php
production_inputs:
  - id
  - production_record_id (FK)
  - box_id (FK, NOT NULL)  ← Solo cajas del stock
  - timestamps
```

**Limitación**: Solo puede vincular `Box` (cajas físicas del stock).

#### ProductionOutput
```php
production_outputs:
  - id
  - production_record_id (FK)
  - product_id (FK)
  - lot_id (string, nullable)
  - boxes (integer)
  - weight_kg (decimal)
```

**Problema**: Los outputs del padre no pueden ser consumidos por los hijos.

### Problemas Derivados

1. **Cálculos Incorrectos**
   - `getTotalInputWeightAttribute()` solo cuenta cajas del stock
   - No considera salidas del padre consumidas
   - Mermas y rendimientos están mal calculados

2. **Trazabilidad Incompleta**
   - No se puede rastrear qué parte de la salida del padre se usó en qué hijo
   - No se puede verificar que la suma de consumos no exceda el output

3. **Lógica de Negocio Incompleta**
   - Un proceso hijo con padre debería poder consumir ambos tipos
   - Actualmente solo puede consumir stock, lo cual no refleja la realidad

## 💡 Solución Propuesta

### Opción 1: Extender ProductionInput (RECOMENDADA)

Hacer `ProductionInput` capaz de representar ambos tipos de inputs:

```php
production_inputs:
  - id
  - production_record_id (FK)
  - box_id (FK, NULLABLE)              ← Input desde stock
  - production_output_id (FK, NULLABLE) ← Input desde output del padre (NUEVO)
  - consumed_weight_kg (DECIMAL, NULLABLE) ← Peso consumido (NUEVO)
  - consumed_boxes (INTEGER, NULLABLE)     ← Cajas consumidas (NUEVO)
  - timestamps
  
  Constraint: Solo uno de box_id o production_output_id debe tener valor
```

**Ventajas**:
- ✅ No rompe estructura existente
- ✅ Reutiliza concepto de `ProductionInput`
- ✅ Consultas simples (todos los inputs en un lugar)
- ✅ Validaciones centralizadas

**Desventajas**:
- ⚠️ Requiere migración de datos (si hay datos existentes)
- ⚠️ Lógica de validación más compleja

### Opción 2: Tabla Separada (NO RECOMENDADA)

Crear tabla nueva `production_output_consumptions`:

**Desventajas**:
- ❌ Duplica lógica de inputs
- ❌ Requiere unir dos tablas para totales
- ❌ Más complejo de mantener

## 🏗️ Implementación Detallada (Opción 1)

### 1. Migración de Base de Datos

```php
Schema::table('production_inputs', function (Blueprint $table) {
    // Hacer box_id nullable
    $table->foreignId('box_id')->nullable()->change();
    
    // Agregar nuevos campos
    $table->foreignId('production_output_id')
        ->nullable()
        ->after('box_id')
        ->constrained('production_outputs')
        ->onDelete('cascade');
    
    $table->decimal('consumed_weight_kg', 10, 2)
        ->nullable()
        ->after('production_output_id');
    
    $table->integer('consumed_boxes')
        ->nullable()
        ->after('consumed_weight_kg');
    
    // Índices
    $table->index('production_output_id');
    
    // Constraint: Solo uno de box_id o production_output_id debe tener valor
    // Nota: Esto se hará en validación de aplicación o trigger
});
```

**Constraint a nivel de aplicación**:
```php
// En el modelo ProductionInput
public static function boot()
{
    parent::boot();
    
    static::saving(function ($input) {
        $hasBox = !is_null($input->box_id);
        $hasOutput = !is_null($input->production_output_id);
        
        if ($hasBox && $hasOutput) {
            throw new \Exception('No se puede tener tanto box_id como production_output_id');
        }
        
        if (!$hasBox && !$hasOutput) {
            throw new \Exception('Debe tener box_id o production_output_id');
        }
        
        // Si es output del padre, debe tener consumed_weight_kg o consumed_boxes
        if ($hasOutput && is_null($input->consumed_weight_kg) && is_null($input->consumed_boxes)) {
            throw new \Exception('Si es input de output del padre, debe especificar peso o cajas consumidas');
        }
    });
}
```

### 2. Cambios en el Modelo ProductionInput

```php
class ProductionInput extends Model
{
    protected $fillable = [
        'production_record_id',
        'box_id',                    // Ahora nullable
        'production_output_id',      // NUEVO
        'consumed_weight_kg',        // NUEVO
        'consumed_boxes',            // NUEVO
    ];
    
    /**
     * Relación con ProductionOutput (cuando el input viene del padre)
     */
    public function productionOutput()
    {
        return $this->belongsTo(ProductionOutput::class, 'production_output_id');
    }
    
    /**
     * Determinar el tipo de input
     */
    public function getInputTypeAttribute()
    {
        if (!is_null($this->box_id)) {
            return 'stock_box';
        }
        if (!is_null($this->production_output_id)) {
            return 'parent_output';
        }
        return null;
    }
    
    /**
     * Obtener el peso del input (considera ambos tipos)
     */
    public function getWeightAttribute()
    {
        if ($this->input_type === 'stock_box') {
            return $this->box->net_weight ?? 0;
        }
        if ($this->input_type === 'parent_output') {
            return $this->consumed_weight_kg ?? 0;
        }
        return 0;
    }
    
    /**
     * Obtener el producto (desde box o desde output)
     */
    public function getProductAttribute()
    {
        if ($this->input_type === 'stock_box') {
            return $this->box->product ?? null;
        }
        if ($this->input_type === 'parent_output') {
            return $this->productionOutput->product ?? null;
        }
        return null;
    }
    
    /**
     * Verificar si viene del stock
     */
    public function isFromStock()
    {
        return $this->input_type === 'stock_box';
    }
    
    /**
     * Verificar si viene de output del padre
     */
    public function isFromParentOutput()
    {
        return $this->input_type === 'parent_output';
    }
}
```

### 3. Cambios en el Modelo ProductionRecord

```php
class ProductionRecord extends Model
{
    /**
     * Obtener el peso total de las entradas (considera ambos tipos)
     */
    public function getTotalInputWeightAttribute()
    {
        // Inputs desde stock (cajas)
        $stockWeight = $this->inputs()
            ->whereNotNull('box_id')
            ->with('box')
            ->get()
            ->sum(function ($input) {
                return $input->box->net_weight ?? 0;
            });
        
        // Inputs desde outputs del padre
        $parentOutputWeight = $this->inputs()
            ->whereNotNull('production_output_id')
            ->sum('consumed_weight_kg');
        
        return $stockWeight + $parentOutputWeight;
    }
    
    /**
     * Obtener inputs que vienen del stock
     */
    public function getStockInputs()
    {
        return $this->inputs()->whereNotNull('box_id')->get();
    }
    
    /**
     * Obtener inputs que vienen de outputs del padre
     */
    public function getParentOutputInputs()
    {
        return $this->inputs()->whereNotNull('production_output_id')->get();
    }
    
    /**
     * Obtener outputs del padre disponibles para consumo
     */
    public function getAvailableParentOutputs()
    {
        if (!$this->parent_record_id) {
            return collect([]); // No tiene padre
        }
        
        $parent = $this->parent;
        if (!$parent) {
            return collect([]);
        }
        
        // Obtener outputs del padre
        $parentOutputs = $parent->outputs;
        
        // Para cada output, calcular cuánto está disponible
        // (output total - ya consumido por este hijo y otros hijos)
        return $parentOutputs->map(function ($output) {
            $consumed = ProductionInput::where('production_output_id', $output->id)
                ->sum('consumed_weight_kg');
            
            $available = $output->weight_kg - $consumed;
            
            return [
                'output' => $output,
                'total_weight' => $output->weight_kg,
                'consumed_weight' => $consumed,
                'available_weight' => max(0, $available),
                'total_boxes' => $output->boxes,
                'consumed_boxes' => ProductionInput::where('production_output_id', $output->id)
                    ->sum('consumed_boxes'),
                'available_boxes' => max(0, $output->boxes - ProductionInput::where('production_output_id', $output->id)
                    ->sum('consumed_boxes')),
            ];
        })->filter(function ($item) {
            return $item['available_weight'] > 0 || $item['available_boxes'] > 0;
        });
    }
}
```

### 4. Cambios en ProductionInputController

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'production_record_id' => 'required|exists:tenant.production_records,id',
        'box_id' => 'nullable|exists:tenant.boxes,id',
        'production_output_id' => 'nullable|exists:tenant.production_outputs,id',
        'consumed_weight_kg' => 'nullable|numeric|min:0',
        'consumed_boxes' => 'nullable|integer|min:0',
    ]);
    
    // Validar que solo uno de box_id o production_output_id tenga valor
    $hasBox = !empty($validated['box_id']);
    $hasOutput = !empty($validated['production_output_id']);
    
    if ($hasBox && $hasOutput) {
        return response()->json([
            'message' => 'No se puede especificar tanto box_id como production_output_id.',
        ], 422);
    }
    
    if (!$hasBox && !$hasOutput) {
        return response()->json([
            'message' => 'Debe especificar box_id o production_output_id.',
        ], 422);
    }
    
    $record = ProductionRecord::findOrFail($validated['production_record_id']);
    
    // Si es input desde output del padre, validar
    if ($hasOutput) {
        $output = ProductionOutput::findOrFail($validated['production_output_id']);
        
        // Validar que el output pertenezca al proceso padre
        if ($record->parent_record_id !== $output->production_record_id) {
            return response()->json([
                'message' => 'El output debe pertenecer al proceso padre directo.',
            ], 422);
        }
        
        // Validar que haya suficiente disponible
        $consumedWeight = ProductionInput::where('production_output_id', $output->id)
            ->sum('consumed_weight_kg');
        
        $availableWeight = $output->weight_kg - $consumedWeight;
        
        if ($validated['consumed_weight_kg'] > $availableWeight) {
            return response()->json([
                'message' => "No hay suficiente output disponible. Disponible: {$availableWeight}kg, solicitado: {$validated['consumed_weight_kg']}kg",
            ], 422);
        }
        
        // Validar que se especifique peso o cajas
        if (empty($validated['consumed_weight_kg']) && empty($validated['consumed_boxes'])) {
            return response()->json([
                'message' => 'Debe especificar consumed_weight_kg o consumed_boxes.',
            ], 422);
        }
    }
    
    // Validar duplicados (para box_id)
    if ($hasBox) {
        $existing = ProductionInput::where('production_record_id', $validated['production_record_id'])
            ->where('box_id', $validated['box_id'])
            ->first();
        
        if ($existing) {
            return response()->json([
                'message' => 'La caja ya está asignada a este proceso.',
            ], 422);
        }
    }
    
    // Validar duplicados (para production_output_id)
    if ($hasOutput) {
        // Permitir múltiples consumos parciales del mismo output
        // Pero validar que la suma no exceda el total
        $existingConsumption = ProductionInput::where('production_record_id', $validated['production_record_id'])
            ->where('production_output_id', $validated['production_output_id'])
            ->sum('consumed_weight_kg');
        
        $totalConsumed = $existingConsumption + ($validated['consumed_weight_kg'] ?? 0);
        
        if ($totalConsumed > $output->weight_kg) {
            return response()->json([
                'message' => 'El consumo total excedería el output disponible.',
            ], 422);
        }
    }
    
    $input = ProductionInput::create($validated);
    $input->load(['productionRecord', 'box.product', 'productionOutput.product']);
    
    return response()->json([
        'message' => 'Entrada de producción creada correctamente.',
        'data' => new ProductionInputResource($input),
    ], 201);
}
```

### 5. Actualización de ProductionInputResource

```php
class ProductionInputResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'productionRecordId' => $this->production_record_id,
            'inputType' => $this->input_type, // 'stock_box' o 'parent_output'
            
            // Si es desde stock
            'boxId' => $this->box_id,
            'box' => $this->when($this->input_type === 'stock_box', function () {
                return [
                    'id' => $this->box->id,
                    'lot' => $this->box->lot,
                    'netWeight' => $this->box->net_weight,
                    'product' => $this->box->product ? [
                        'id' => $this->box->product->id,
                        'name' => $this->box->product->name,
                    ] : null,
                ];
            }),
            
            // Si es desde output del padre
            'productionOutputId' => $this->production_output_id,
            'productionOutput' => $this->when($this->input_type === 'parent_output', function () {
                return [
                    'id' => $this->productionOutput->id,
                    'product' => $this->productionOutput->product ? [
                        'id' => $this->productionOutput->product->id,
                        'name' => $this->productionOutput->product->name,
                    ] : null,
                    'totalWeight' => $this->productionOutput->weight_kg,
                    'totalBoxes' => $this->productionOutput->boxes,
                    'consumedWeight' => $this->consumed_weight_kg,
                    'consumedBoxes' => $this->consumed_boxes,
                ];
            }),
            
            'weight' => $this->weight,
            'product' => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ] : null,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
```

### 6. Validaciones Adicionales

```php
// En ProductionRecord
public function canConsumeOutput(ProductionOutput $output, float $weight, ?int $boxes = null)
{
    // Verificar que el output pertenezca al padre
    if ($this->parent_record_id !== $output->production_record_id) {
        return [
            'valid' => false,
            'message' => 'El output debe pertenecer al proceso padre directo.',
        ];
    }
    
    // Calcular consumo actual
    $consumedWeight = ProductionInput::where('production_output_id', $output->id)
        ->sum('consumed_weight_kg');
    
    $availableWeight = $output->weight_kg - $consumedWeight;
    
    if ($weight > $availableWeight) {
        return [
            'valid' => false,
            'message' => "No hay suficiente peso disponible. Disponible: {$availableWeight}kg, solicitado: {$weight}kg",
        ];
    }
    
    if (!is_null($boxes)) {
        $consumedBoxes = ProductionInput::where('production_output_id', $output->id)
            ->sum('consumed_boxes');
        
        $availableBoxes = $output->boxes - $consumedBoxes;
        
        if ($boxes > $availableBoxes) {
            return [
                'valid' => false,
                'message' => "No hay suficientes cajas disponibles. Disponible: {$availableBoxes}, solicitado: {$boxes}",
            ];
        }
    }
    
    return [
        'valid' => true,
        'availableWeight' => $availableWeight,
        'availableBoxes' => $availableBoxes ?? null,
    ];
}
```

## 📊 Impacto en Cálculos

### Antes (Incorrecto)

```php
Proceso Hijo "Envasado":
  - Inputs desde stock: 50kg
  - Total Input Weight: 50kg ❌ (falta el consumo del padre)
```

### Después (Correcto)

```php
Proceso Hijo "Envasado":
  - Inputs desde stock: 50kg
  - Inputs desde padre: 150kg
  - Total Input Weight: 200kg ✅
```

## 🔄 Flujo de Trabajo Actualizado

1. **Crear proceso padre** y asignarle cajas del stock
2. **Registrar output del padre** (ej: 300kg de filetes)
3. **Crear proceso hijo** con `parent_record_id` apuntando al padre
4. **Consumir output del padre**:
   ```json
   POST /v2/production-inputs
   {
       "production_record_id": 123,  // ID del proceso hijo
       "production_output_id": 456,  // ID del output del padre
       "consumed_weight_kg": 150.00,
       "consumed_boxes": 10
   }
   ```
5. **Opcionalmente consumir más cajas del stock**:
   ```json
   POST /v2/production-inputs
   {
       "production_record_id": 123,
       "box_id": 789
   }
   ```

## ✅ Validaciones Críticas

1. ✅ Solo uno de `box_id` o `production_output_id` puede tener valor
2. ✅ El `production_output_id` debe pertenecer al proceso padre directo
3. ✅ El consumo no puede exceder el output disponible
4. ✅ Si se consume por peso, `consumed_weight_kg` es requerido
5. ✅ Si se consume por cajas, `consumed_boxes` es requerido
6. ✅ Un output puede ser consumido parcialmente por múltiples hijos

## 🚨 Consideraciones Importantes

1. **Consumo Parcial**: Un output del padre puede ser consumido parcialmente por múltiples hijos
   - Ejemplo: Output de 300kg puede ser consumido 150kg por hijo A y 100kg por hijo B, quedando 50kg sin consumir

2. **Unicidad**: 
   - Para `box_id`: unique constraint (una caja solo una vez en el mismo proceso)
   - Para `production_output_id`: múltiples consumos parciales permitidos (pero suma controlada)

3. **Cascadas**: 
   - Si se elimina un `ProductionOutput`, eliminar todos los `ProductionInput` que lo consumen
   - Si se elimina un `ProductionRecord`, eliminar todos sus `ProductionInput` (incluyendo los que consumen outputs)

4. **Backward Compatibility**:
   - Los `ProductionInput` existentes (solo con `box_id`) seguirán funcionando
   - Los cálculos existentes se actualizarán automáticamente para incluir ambos tipos

## 📝 Checklist de Implementación

- [ ] Crear migración para agregar campos a `production_inputs`
- [ ] Actualizar modelo `ProductionInput` con nuevos campos y relaciones
- [ ] Agregar métodos helper en `ProductionInput` (`getInputTypeAttribute`, etc.)
- [ ] Actualizar `ProductionRecord::getTotalInputWeightAttribute()` para considerar ambos tipos
- [ ] Agregar método `getAvailableParentOutputs()` en `ProductionRecord`
- [ ] Actualizar `ProductionInputController::store()` con validaciones
- [ ] Actualizar `ProductionInputResource` para incluir ambos tipos
- [ ] Actualizar documentación de la API
- [ ] Agregar tests unitarios para nuevas funcionalidades
- [ ] Actualizar métodos de cálculo en `ProductionRecord::calculateNodeTotals()`
- [ ] Actualizar `ProductionRecord::getNodeData()` para incluir inputs de outputs del padre

## 🔗 Referencias

- [13-Produccion-Entradas.md](./13-Produccion-Entradas.md) - Documentación actual de ProductionInput
- [14-Produccion-Salidas.md](./14-Produccion-Salidas.md) - Documentación de ProductionOutput
- [12-Produccion-Procesos.md](./12-Produccion-Procesos.md) - Documentación de ProductionRecord

