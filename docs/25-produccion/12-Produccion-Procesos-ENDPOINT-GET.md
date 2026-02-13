# Endpoint GET Production Records - Qué Devuelve Exactamente

**Endpoint**: `GET /v2/production-records/{id}`  
**Método**: `ProductionRecordController@show()`  
**Fecha**: 2025-01-27

---

## 📋 Resumen Ejecutivo

El endpoint `GET /v2/production-records/{id}` devuelve:

- ✅ **SÍ devuelve**: Inputs (cajas del stock)
- ✅ **SÍ devuelve**: Outputs (productos producidos)
- ❌ **NO devuelve**: Consumos (parentOutputConsumptions) en el endpoint normal
- ✅ **SÍ devuelve**: Consumos en el endpoint `/tree`

---

## 🔍 Análisis del Código

### Método `show()` del Controlador

```php
public function show(string $id)
{
    $record = ProductionRecord::with([
        'production',
        'parent',
        'children',
        'process',
        'inputs.box.product',      // ✅ CARGADO
        'outputs.product'           // ✅ CARGADO
        // ❌ parentOutputConsumptions NO se carga aquí
    ])->findOrFail($id);

    return response()->json([
        'message' => 'Registro de producción obtenido correctamente.',
        'data' => new ProductionRecordResource($record),
    ]);
}
```

### ProductionRecordResource

El resource devuelve:

```php
return [
    'id' => $this->id,
    'productionId' => $this->production_id,
    'production' => [...],           // ✅ Si está cargado
    'parentRecordId' => $this->parent_record_id,
    'parent' => [...],                // ✅ Si está cargado
    'processId' => $this->process_id,
    'process' => [...],               // ✅ Si está cargado
    'startedAt' => $this->started_at,
    'finishedAt' => $this->finished_at,
    'notes' => $this->notes,
    'isRoot' => $this->isRoot(),
    'isFinal' => $this->isFinal(),
    'isCompleted' => $this->isCompleted(),
    'totalInputWeight' => $inputWeight,    // ✅ Calculado (incluye consumos en el cálculo)
    'totalOutputWeight' => $outputWeight,  // ✅ Calculado
    'totalInputBoxes' => $this->total_input_boxes,  // ✅ Calculado (incluye consumos)
    'totalOutputBoxes' => $this->total_output_boxes, // ✅ Calculado
    'waste' => $waste,
    'wastePercentage' => $wastePercentage,
    'yield' => $yield,
    'yieldPercentage' => $yieldPercentage,
    'inputs' => ProductionInputResource::collection($this->whenLoaded('inputs')),  // ✅ DEVUELVE
    'outputs' => ProductionOutputResource::collection($this->whenLoaded('outputs')), // ✅ DEVUELVE
    'children' => ProductionRecordResource::collection($this->whenLoaded('children')), // ✅ Si tiene hijos
    'createdAt' => $this->created_at,
    'updatedAt' => $this->updated_at,
    // ❌ parentOutputConsumptions NO se incluye en el array
];
```

---

## ✅ Lo que SÍ Devuelve

### 1. Inputs (Entradas desde Stock)

**Relación cargada**: `inputs.box.product`

**Estructura devuelta** (ProductionInputResource):
```json
{
  "inputs": [
    {
      "id": 1,
      "productionRecordId": 5,
      "boxId": 123,
      "box": {
        "id": 123,
        "lot": "LOTE-001",
        "netWeight": 25.5,
        "grossWeight": 27.0,
        "product": {
          "id": 10,
          "name": "Producto A"
        }
      },
      "product": {
        "id": 10,
        "name": "Producto A"
      },
      "lot": "LOTE-001",
      "weight": 25.5,
      "createdAt": "2025-01-27T10:00:00Z",
      "updatedAt": "2025-01-27T10:00:00Z"
    }
  ]
}
```

**Tipo**: Cajas directamente asignadas desde el stock/almacén al proceso.

---

### 2. Outputs (Salidas - Productos Producidos)

**Relación cargada**: `outputs.product`

**Estructura devuelta** (ProductionOutputResource):
```json
{
  "outputs": [
    {
      "id": 1,
      "productionRecordId": 5,
      "productId": 20,
      "product": {
        "id": 20,
        "name": "Producto B"
      },
      "lotId": "LOTE-002",
      "boxes": 10,
      "weightKg": 250.0,
      "averageWeightPerBox": 25.0,
      "createdAt": "2025-01-27T10:00:00Z",
      "updatedAt": "2025-01-27T10:00:00Z"
    }
  ]
}
```

**Tipo**: Productos producidos por el proceso.

---

### 3. Totales Calculados

Los totales **SÍ incluyen** los consumos en el cálculo, aunque no se devuelvan los consumos individuales:

```json
{
  "totalInputWeight": 500.0,    // ✅ Incluye: inputs de stock + consumos del padre
  "totalOutputWeight": 450.0,    // ✅ Suma de todos los outputs
  "totalInputBoxes": 20,         // ✅ Incluye: cajas de stock + cajas consumidas del padre
  "totalOutputBoxes": 18,        // ✅ Suma de todas las cajas de outputs
  "waste": 50.0,                 // ✅ Calculado: input - output (si hay pérdida)
  "wastePercentage": 10.0,       // ✅ Porcentaje de merma
  "yield": 0,                    // ✅ Calculado: output - input (si hay ganancia)
  "yieldPercentage": 0           // ✅ Porcentaje de rendimiento
}
```

**Nota importante**: Los totales se calculan usando los accessors del modelo que **SÍ incluyen** los consumos:
- `getTotalInputWeightAttribute()` → Incluye `parentOutputConsumptions()->sum('consumed_weight_kg')`
- `getTotalInputBoxesAttribute()` → Incluye `parentOutputConsumptions()->sum('consumed_boxes')`

---

## ✅ Consumos (Parent Output Consumptions) - AHORA INCLUIDOS

**Relación**: `parentOutputConsumptions` (cargada y devuelta)

**Estructura devuelta**:
```json
{
  "parentOutputConsumptions": [
    {
      "id": 1,
      "productionRecordId": 5,
      "productionOutputId": 8,
      "consumedWeightKg": 200.0,
      "consumedBoxes": 8,
      "notes": "...",
      "productionOutput": {
        "id": 8,
        "productId": 15,
        "product": {...},
        "weightKg": 500.0,
        "boxes": 20
      },
      "product": {...},
      "parentRecord": {
        "id": 2,
        "process": {...}
      },
      "isComplete": false,
      "isPartial": true,
      "weightConsumptionPercentage": 40.0,
      "outputAvailableWeight": 300.0,
      "outputAvailableBoxes": 12
    }
  ]
}
```

**Información incluida**:
- ✅ Qué outputs del padre se consumieron
- ✅ Cuánto se consumió de cada output (peso y cajas)
- ✅ Información completa del output consumido
- ✅ Información del proceso padre
- ✅ Porcentajes de consumo
- ✅ Disponibilidad restante del output

---

## 🔄 Endpoint Alternativo: `/tree`

Si necesitas los consumos, usa el endpoint:

**`GET /v2/production-records/{id}/tree`**

Este endpoint:
1. ✅ Carga `parentOutputConsumptions.productionOutput.product`
2. ✅ Construye el árbol recursivo de hijos
3. ✅ Devuelve los consumos en el método `getNodeData()` del modelo

**Código del método `tree()`**:
```php
public function tree(string $id)
{
    $record = ProductionRecord::with([
        'production',
        'parent',
        'process',
        'inputs.box.product',
        'outputs.product'
        // ❌ Tampoco carga parentOutputConsumptions aquí explícitamente
    ])->findOrFail($id);

    $record->buildTree();  // ✅ Este método SÍ carga los consumos

    return response()->json([
        'message' => 'Árbol de procesos obtenido correctamente.',
        'data' => new ProductionRecordResource($record),
    ]);
}
```

**Método `buildTree()` del modelo**:
```php
public function buildTree()
{
    $this->load('children.process', 'inputs.box.product', 'outputs.product', 
                'parentOutputConsumptions.productionOutput.product');  // ✅ Carga consumos
    
    foreach ($this->children as $child) {
        $child->buildTree();
    }
    
    return $this;
}
```

**PERO**: Aunque `buildTree()` carga los consumos, el `ProductionRecordResource` **NO los incluye** en el array devuelto, así que tampoco se devuelven en `/tree`.

---

## 📊 Comparación de Endpoints

| Dato | `GET /v2/production-records/{id}` | `GET /v2/production-records/{id}/tree` |
|------|-----------------------------------|----------------------------------------|
| **Inputs (stock)** | ✅ Sí | ✅ Sí |
| **Outputs** | ✅ Sí | ✅ Sí |
| **Consumos** | ✅ Sí ✨ | ✅ Sí (si se cargan) |
| **Children** | ✅ Sí (si tiene) | ✅ Sí (árbol completo) |
| **Totales** | ✅ Sí (incluyen consumos) | ✅ Sí (incluyen consumos) |

---

## ✅ Solución Implementada

### Cambios Realizados

Los consumos (`parentOutputConsumptions`) **AHORA SE DEVUELVEN** en el endpoint GET:

1. ✅ **Controlador actualizado**: El método `show()` ahora carga los consumos:
   ```php
   $record = ProductionRecord::with([
       'production',
       'parent',
       'children',
       'process',
       'inputs.box.product',
       'outputs.product',
       'parentOutputConsumptions.productionOutput.product'  // ✅ Agregado
   ])->findOrFail($id);
   ```

2. ✅ **Resource actualizado**: `ProductionRecordResource` ahora incluye los consumos:
   ```php
   'parentOutputConsumptions' => ProductionOutputConsumptionResource::collection(
       $this->whenLoaded('parentOutputConsumptions')
   ),
   ```

### Ejemplo Completo

Ver el ejemplo completo en:
- **JSON**: `docs/32-ejemplos/EJEMPLO-RESPUESTA-production-record-completo.json`
- **Documentación**: `docs/32-ejemplos/EJEMPLO-RESPUESTA-production-record-completo.md`

---

## 📝 Resumen Final

### Endpoint `GET /v2/production-records/{id}`

**Devuelve**:
- ✅ Datos básicos del record (id, fechas, notas, etc.)
- ✅ Información de producción, padre, proceso
- ✅ **Inputs completos** (cajas del stock con sus productos)
- ✅ **Outputs completos** (productos producidos)
- ✅ **Consumos completos** ✨ **NUEVO** (consumos de outputs del padre)
- ✅ **Totales calculados** (que incluyen consumos en el cálculo)
- ✅ **Children** (si tiene procesos hijos)

**Estructura de consumos**:
- ✅ Lista completa de consumos (`parentOutputConsumptions`)
- ✅ Detalles de qué outputs del padre se consumieron
- ✅ Cuánto se consumió de cada output del padre
- ✅ Información del output consumido y del proceso padre
- ✅ Disponibilidad restante de cada output

---

## 🔗 Referencias

- **Controlador**: `app/Http/Controllers/v2/ProductionRecordController.php`
- **Resource**: `app/Http/Resources/v2/ProductionRecordResource.php`
- **Modelo**: `app/Models/ProductionRecord.php`
- **Documentación**: `docs/25-produccion/12-Produccion-Procesos.md`

---

**Última actualización**: 2025-01-27

