# Producción - Gestión de Lotes (Production)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Production` representa la **cabecera de un lote completo de producción**. Es la entidad principal que agrupa todos los procesos relacionados con una producción pesquera específica.

**Archivo del modelo**: `app/Models/Production.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `productions`

**Migraciones**:
- `database/migrations/companies/2024_10_31_224228_create_productions_table.php` (creación inicial)
- `database/migrations/companies/2025_11_23_135210_update_productions_table_for_new_structure.php` (actualización para v2)

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del lote |
| `lot` | string | YES | Identificador del lote (ej: "LOT-2024-001") |
| `date` | date | YES | **LEGACY**: Fecha de la producción (v1) |
| `species_id` | bigint | YES | FK a `species` - Especie pesquera (nullable en v2) |
| `capture_zone_id` | bigint | YES | **LEGACY**: FK a `capture_zones` - Zona de captura (v1) |
| `notes` | text | YES | Notas adicionales del lote |
| `diagram_data` | json | YES | **LEGACY**: JSON completo del diagrama (v1) |
| `opened_at` | timestamp | YES | Fecha/hora cuando se abre el lote (v2) |
| `closed_at` | timestamp | YES | Fecha/hora cuando se cierra el lote (v2) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign keys a `species` y `capture_zones`

**Constraints**:
- `species_id` → `species.id` (onDelete: cascade)
- `capture_zone_id` → `capture_zones.id` (onDelete: cascade)

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'lot',
    'date',              // LEGACY
    'species_id',
    'capture_zone_id',   // LEGACY
    'notes',
    'diagram_data',      // LEGACY
    'opened_at',
    'closed_at',
];
```

### Casts

```php
protected $casts = [
    'diagram_data' => 'array',   // JSON se convierte en array PHP
    'date' => 'date',
    'opened_at' => 'datetime',
    'closed_at' => 'datetime',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### Relaciones Principales

#### 1. `species()` - Especie Pesquera
```php
public function species()
{
    return $this->belongsTo(Species::class, 'species_id');
}
```
- Relación muchos-a-uno con `Species`
- Nullable en v2

#### 2. `captureZone()` - Zona de Captura (LEGACY)
```php
public function captureZone()
{
    return $this->belongsTo(CaptureZone::class, 'capture_zone_id');
}
```
- Relación muchos-a-uno con `CaptureZone`
- Campo legacy de v1

### Relaciones v2 (Nueva Estructura)

#### 3. `records()` - Procesos del Lote
```php
public function records()
{
    return $this->hasMany(ProductionRecord::class, 'production_id');
}
```
- Relación uno-a-muchos con `ProductionRecord`
- Retorna todos los procesos del lote

#### 4. `rootRecords()` - Procesos Raíz
```php
public function rootRecords()
{
    return $this->hasMany(ProductionRecord::class, 'production_id')
        ->whereNull('parent_record_id');
}
```
- Retorna solo los procesos sin padre (inicio del árbol)

#### 5. `allInputs()` - Todas las Entradas del Lote
```php
public function allInputs()
{
    return ProductionInput::whereIn('production_record_id', function ($query) {
        $query->select('id')
            ->from('production_records')
            ->where('production_id', $this->id);
    });
}
```
- Query builder (no relación Eloquent directa)
- Retorna todas las entradas a través de todos los procesos

#### 6. `allOutputs()` - Todas las Salidas del Lote
```php
public function allOutputs()
{
    return ProductionOutput::whereIn('production_record_id', function ($query) {
        $query->select('id')
            ->from('production_records')
            ->where('production_id', $this->id);
    });
}
```
- Query builder (no relación Eloquent directa)
- Retorna todas las salidas a través de todos los procesos

---

## 🎯 Métodos de Estado

### `isOpen()` - Verificar si está Abierto
```php
public function isOpen()
{
    return $this->opened_at !== null && $this->closed_at === null;
}
```
- Retorna `true` si el lote está abierto (puede agregar procesos)

### `isClosed()` - Verificar si está Cerrado
```php
public function isClosed()
{
    return $this->closed_at !== null;
}
```
- Retorna `true` si el lote está cerrado (no admite modificaciones)

### `open()` - Abrir el Lote
```php
public function open()
{
    if ($this->opened_at === null) {
        $this->update(['opened_at' => now()]);
    }
    return $this;
}
```
- Establece `opened_at` si no está ya abierto
- Retorna la instancia para chaining

### `close()` - Cerrar el Lote
```php
public function close()
{
    if ($this->closed_at === null) {
        $this->update(['closed_at' => now()]);
    }
    return $this;
}
```
- Establece `closed_at` si no está ya cerrado
- Retorna la instancia para chaining

**Nota**: Actualmente no hay validaciones antes de cerrar (ver observaciones críticas)

---

## 🧮 Métodos de Cálculo

### `buildProcessTree()` - Construir Árbol de Procesos
```php
public function buildProcessTree()
{
    $rootRecords = $this->rootRecords()
        ->with(['process', 'inputs.box.product', 'outputs.product'])
        ->get();

    foreach ($rootRecords as $record) {
        $record->buildTree();
    }

    return $rootRecords;
}
```
- Obtiene procesos raíz con relaciones eager loaded
- Construye árbol recursivamente desde cada raíz
- Retorna colección de `ProductionRecord` con árbol completo

### `calculateDiagram()` - Calcular Diagrama Completo
```php
public function calculateDiagram()
{
    $rootRecords = $this->buildProcessTree();
    
    $processNodes = $rootRecords->map(function ($record) {
        return $record->getNodeData();
    })->toArray();
    
    $globalTotals = $this->calculateGlobalTotals();
    
    return [
        'processNodes' => $processNodes,
        'totals' => $globalTotals,
    ];
}
```
- Calcula diagrama completo desde estructura relacional
- Retorna formato compatible con `diagram_data` antiguo
- Incluye totales globales

### `getDiagramData()` - Obtener Diagrama (Compatible)
```php
public function getDiagramData()
{
    // Si existe diagram_data antiguo y no hay procesos nuevos, retornarlo
    if ($this->diagram_data && $this->records()->count() === 0) {
        return $this->diagram_data;
    }
    
    // Calcular dinámicamente desde los procesos
    return $this->calculateDiagram();
}
```
- **Compatibilidad**: Retorna datos antiguos si no hay procesos nuevos
- **v2**: Calcula dinámicamente desde estructura relacional
- Usado por endpoint `/v2/productions/{id}/diagram`

### `calculateGlobalTotals()` - Calcular Totales Globales
```php
public function calculateGlobalTotals()
{
    $totalInputWeight = $this->total_input_weight;
    $totalOutputWeight = $this->total_output_weight;
    $totalWaste = $totalInputWeight - $totalOutputWeight;
    $totalWastePercentage = $totalInputWeight > 0 
        ? ($totalWaste / $totalInputWeight) * 100 
        : 0;
    
    $totalInputBoxes = $this->total_input_boxes;
    $totalOutputBoxes = $this->total_output_boxes;
    
    return [
        'totalInputWeight' => round($totalInputWeight, 2),
        'totalOutputWeight' => round($totalOutputWeight, 2),
        'totalWaste' => round($totalWaste, 2),
        'totalWastePercentage' => round($totalWastePercentage, 2),
        'totalInputBoxes' => $totalInputBoxes,
        'totalOutputBoxes' => $totalOutputBoxes,
    ];
}
```
- Calcula totales agregados de todo el lote
- Retorna array con pesos, mermas y cantidades de cajas

### Attributes Calculados

#### `getTotalInputWeightAttribute()` - Peso Total de Entrada
```php
public function getTotalInputWeightAttribute()
{
    return $this->allInputs()
        ->join('boxes', 'production_inputs.box_id', '=', 'boxes.id')
        ->sum('boxes.net_weight');
}
```
- Suma el `net_weight` de todas las cajas en inputs
- **Problema**: Ejecuta query en cada acceso (ver observaciones)

#### `getTotalOutputWeightAttribute()` - Peso Total de Salida
```php
public function getTotalOutputWeightAttribute()
{
    return $this->allOutputs()->sum('weight_kg');
}
```
- Suma el `weight_kg` de todos los outputs
- **Problema**: Ejecuta query en cada acceso (ver observaciones)

#### `getTotalInputBoxesAttribute()` - Número Total de Cajas de Entrada
```php
public function getTotalInputBoxesAttribute()
{
    return $this->allInputs()->count();
}
```
- Cuenta todas las entradas

#### `getTotalOutputBoxesAttribute()` - Número Total de Cajas de Salida
```php
public function getTotalOutputBoxesAttribute()
{
    return $this->allOutputs()->sum('boxes');
}
```
- Suma la cantidad de cajas declaradas en outputs

#### `getTotalWasteAttribute()` - Merma Total
```php
public function getTotalWasteAttribute()
{
    return $this->total_input_weight - $this->total_output_weight;
}
```
- Diferencia entre peso entrada y salida

#### `getWastePercentageAttribute()` - Porcentaje de Merma
```php
public function getWastePercentageAttribute()
{
    if ($this->total_input_weight > 0) {
        return ($this->total_waste / $this->total_input_weight) * 100;
    }
    return 0;
}
```
- Porcentaje de merma respecto al peso de entrada

---

## 🔍 Métodos de Conciliación

### `getStockBoxes()` - Cajas del Lote en Stock
```php
public function getStockBoxes()
{
    return Box::where('lot', $this->lot)
        ->whereHas('palletBox')
        ->get();
}
```
- Busca cajas con `lot` coincidente que estén en palets
- Retorna colección de `Box`

### `getStockWeightAttribute()` - Peso en Stock
```php
public function getStockWeightAttribute()
{
    return Box::where('lot', $this->lot)
        ->whereHas('palletBox')
        ->sum('net_weight');
}
```
- Suma peso de cajas del lote que están en stock

### `getStockBoxesCountAttribute()` - Cantidad de Cajas en Stock
```php
public function getStockBoxesCountAttribute()
{
    return Box::where('lot', $this->lot)
        ->whereHas('palletBox')
        ->count();
}
```
- Cuenta cajas del lote que están en stock

### `reconcile()` - Realizar Conciliación
```php
public function reconcile()
{
    $declaredBoxes = $this->total_output_boxes;
    $declaredWeight = $this->total_output_weight;
    $stockBoxes = $this->stock_boxes_count;
    $stockWeight = $this->stock_weight;
    
    $boxDifference = abs($declaredBoxes - $stockBoxes);
    $weightDifference = abs($declaredWeight - $stockWeight);
    
    $boxPercentage = $declaredBoxes > 0 
        ? ($boxDifference / $declaredBoxes) * 100 
        : 0;
    $weightPercentage = $declaredWeight > 0 
        ? ($weightDifference / $declaredWeight) * 100 
        : 0;
    
    // Determinar estado (umbrales hardcodeados)
    $status = 'green';
    if ($boxPercentage > 5 || $weightPercentage > 5) {
        $status = 'red';
    } elseif ($boxPercentage > 1 || $weightPercentage > 1) {
        $status = 'yellow';
    }
    
    return [
        'status' => $status,
        'declared' => [
            'boxes' => $declaredBoxes,
            'weight_kg' => $declaredWeight,
        ],
        'stock' => [
            'boxes' => $stockBoxes,
            'weight_kg' => $stockWeight,
        ],
        'differences' => [
            'boxes' => $boxDifference,
            'weight_kg' => $weightDifference,
            'box_percentage' => round($boxPercentage, 2),
            'weight_percentage' => round($weightPercentage, 2),
        ],
    ];
}
```
- Compara producción declarada vs stock real
- Retorna estado: `green` (<1%), `yellow` (1-5%), `red` (>5%)
- **Problema**: Umbrales hardcodeados (ver observaciones)

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/ProductionController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Producciones
```php
GET /v2/productions
```

**Parámetros de query**:
- `lot` (string): Filtrar por lote (LIKE)
- `species_id` (integer): Filtrar por especie
- `status` (string): `open` o `closed` para filtrar por estado
- `perPage` (integer, default: 15): Cantidad por página

**Relaciones cargadas**: `species`, `captureZone`, `records`

**Ordenamiento**: Por `opened_at` descendente

#### `store(Request $request)` - Crear Producción
```php
POST /v2/productions
```

**Validación**:
```php
[
    'lot' => 'nullable|string',
    'species_id' => 'nullable|exists:tenant.species,id',
    'notes' => 'nullable|string',
]
```

**Comportamiento**:
- Crea el registro
- Automáticamente llama a `open()` para abrir el lote

**Respuesta**: 201 con datos del lote creado

#### `show(string $id)` - Mostrar Producción
```php
GET /v2/productions/{id}
```

**Relaciones cargadas**: `species`, `captureZone`, `records.process`

#### `update(Request $request, string $id)` - Actualizar Producción
```php
PUT /v2/productions/{id}
```

**Validación**:
```php
[
    'lot' => 'sometimes|nullable|string',
    'species_id' => 'sometimes|nullable|exists:tenant.species,id',
    'notes' => 'sometimes|nullable|string',
]
```

**Nota**: No permite actualizar `opened_at` o `closed_at` (debe usarse `open()`/`close()`)

#### `destroy(string $id)` - Eliminar Producción
```php
DELETE /v2/productions/{id}
```

**Comportamiento**: Elimina el lote y todas sus relaciones (cascade)

#### `getDiagram(string $id)` - Obtener Diagrama
```php
GET /v2/productions/{id}/diagram
```

**Comportamiento**: Llama a `getDiagramData()` del modelo

#### `getProcessTree(string $id)` - Obtener Árbol de Procesos
```php
GET /v2/productions/{id}/process-tree
```

**Retorna**: Estructura del árbol con totales globales

#### `getTotals(string $id)` - Obtener Totales
```php
GET /v2/productions/{id}/totals
```

**Comportamiento**: Llama a `calculateGlobalTotals()`

#### `getReconciliation(string $id)` - Obtener Conciliación
```php
GET /v2/productions/{id}/reconciliation
```

**Comportamiento**: Llama a `reconcile()`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/ProductionResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "lot": "LOT-2024-001",
    "speciesId": 5,
    "species": {
        "id": 5,
        "name": "Atún"
    },
    "captureZoneId": 2,
    "captureZone": {
        "id": 2,
        "name": "Atlántico Norte"
    },
    "notes": "Lote de prueba",
    "openedAt": "2024-01-15T10:30:00Z",
    "closedAt": null,
    "isOpen": true,
    "isClosed": false,
    "date": "2024-01-15",
    "diagramData": {...},  // Solo si ?include_diagram=true
    "totals": {...},       // Solo si ?include_totals=true
    "records": [...],      // Solo si relación cargada
    "createdAt": "2024-01-15T10:30:00Z",
    "updatedAt": "2024-01-15T10:30:00Z"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/productions/*`

---

## 📝 Ejemplos de Uso

### Crear un Lote y Abrirlo
```http
POST /v2/productions
Content-Type: application/json
X-Tenant: empresa1

{
    "lot": "LOT-2024-001",
    "species_id": 5,
    "notes": "Producción de atún"
}
```

### Obtener Diagrama Completo
```http
GET /v2/productions/1/diagram?include_diagram=true&include_totals=true
```

### Obtener Conciliación
```http
GET /v2/productions/1/reconciliation
```

### Filtrar Lotes Abiertos
```http
GET /v2/productions?status=open&perPage=20
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Métodos Legacy Sin Equivalente v2

1. **`getProcessNodes()` y `getFinalNodes()`** (`app/Models/Production.php:65-141`)
   - Solo funcionan con `diagram_data` antiguo (v1)
   - No tienen equivalente para estructura relacional
   - **Líneas**: 65-80, 82-141
   - **Problema**: Si se elimina `diagram_data`, estos métodos dejarán de funcionar
   - **Recomendación**: Crear métodos equivalentes usando `buildProcessTree()` o deprecar

### ⚠️ Performance: Queries N+1 en Attributes

2. **Attributes Calculados con Queries** (`app/Models/Production.php:338-367`)
   - `total_input_weight`, `total_output_weight`, etc. ejecutan queries en cada acceso
   - **Líneas**: 338-343, 348-351, 356-359, 364-367
   - **Problema**: Si se accede múltiples veces (ej: en un loop), se ejecutan múltiples queries
   - **Recomendación**: 
     - Cachear resultados con `Cache::remember()`
     - O usar eager loading agregado en controlador
     - O calcular una vez y almacenar en atributos del modelo

### ⚠️ Validaciones Faltantes

3. **No Validar Estado Antes de Operaciones** (`app/Http/Controllers/v2/ProductionController.php`)
   - `update()` y otros métodos no validan si el lote está cerrado
   - **Líneas**: 87-103
   - **Problema**: Pueden modificarse lotes cerrados
   - **Recomendación**: Agregar validación `if ($production->isClosed()) return error`

4. **No Validar Antes de Cerrar** (`app/Models/Production.php:246-252`)
   - `close()` no valida que todos los procesos estén finalizados
   - **Líneas**: 246-252
   - **Problema**: Pueden cerrarse lotes con procesos pendientes
   - **Recomendación**: Agregar método `canClose()` que valide:
     - Todos los procesos tienen `finished_at`
     - Conciliación aceptable (status != 'red')

5. **Species_id Nullable Sin Validación** (`app/Http/Controllers/v2/ProductionController.php:53-57`)
   - En v1 era required, en v2 es nullable
   - **Líneas**: 55
   - **Problema**: Puede haber inconsistencias si frontend asume que siempre existe
   - **Recomendación**: Validar en frontend o hacer required si es necesario para negocio

### ⚠️ Umbrales Hardcodeados

6. **Conciliación con Umbrales Fijos** (`app/Models/Production.php:440-445`)
   - Umbrales (5% red, 1% yellow) están hardcodeados
   - **Líneas**: 440-445
   - **Problema**: No son configurables por tenant o tipo de producto
   - **Recomendación**: 
     - Mover a tabla `settings` o `production_config`
     - O permitir configuración por tenant

### ⚠️ Conciliación Basada Solo en `lot`

7. **Conciliación Asume Mismo `lot`** (`app/Models/Production.php:395-464`)
   - `reconcile()` busca cajas por `lot` coincidente
   - **Líneas**: 397, 407, 417
   - **Problema**: Si `lot` es diferente o vacío, conciliación falla
   - **Recomendación**: 
     - Validar que `lot` esté presente
     - O usar otro método de matching (ej: por fechas, por inputs)

### ⚠️ Manejo de División por Cero

8. **Falta Validación en Cálculos** (`app/Models/Production.php:315-333`)
   - `calculateGlobalTotals()` valida división por cero en porcentaje
   - Pero `getWastePercentageAttribute()` también valida
   - **Líneas**: 315-333, 380-386
   - **Problema**: Consistencia, pero algunos edge cases pueden pasar
   - **Recomendación**: Agregar validaciones defensivas más robustas

### ⚠️ Compatibilidad Legacy Compleja

9. **Lógica de Compatibilidad en `getDiagramData()`** (`app/Models/Production.php:301-310`)
   - Retorna datos antiguos si no hay procesos nuevos
   - **Líneas**: 301-310
   - **Problema**: Si hay datos en ambos formatos, puede haber confusión
   - **Recomendación**: 
     - Priorizar siempre estructura nueva si existe
     - O tener flag explícito `use_legacy_diagram`

### ⚠️ Campos Legacy en Fillable

10. **Fillable Incluye Campos Legacy** (`app/Models/Production.php:15-24`)
    - `date`, `capture_zone_id`, `diagram_data` en fillable
    - **Líneas**: 16, 18, 19, 21
    - **Problema**: Permiten actualización accidental desde API v2
    - **Recomendación**: 
      - Eliminar de fillable en v2
      - O crear validación que los ignore en v2

### ⚠️ Falta de Soft Deletes

11. **Eliminación Hard Delete** (`app/Http/Controllers/v2/ProductionController.php:108-116`)
    - `destroy()` hace hard delete
    - **Líneas**: 108-116
    - **Problema**: No permite recuperar lotes eliminados por error
    - **Recomendación**: Implementar soft deletes con `SoftDeletes` trait

### ⚠️ Relaciones `allInputs()` y `allOutputs()` No Son Eloquent

12. **Query Builder en Lugar de Relación** (`app/Models/Production.php:191-210`)
    - `allInputs()` y `allOutputs()` retornan query builder, no relación
    - **Líneas**: 191-198, 203-210
    - **Problema**: No pueden usarse con eager loading estándar
    - **Recomendación**: 
      - Mantener como está (query builder es necesario)
      - O documentar claramente que son query builders

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

