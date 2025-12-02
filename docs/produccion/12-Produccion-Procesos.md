# Producción - Procesos (ProductionRecord)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `ProductionRecord` representa un **proceso individual** dentro de un lote de producción. Los procesos se organizan en estructuras jerárquicas (árboles) donde un proceso puede tener procesos hijos que representan subprocesos o transformaciones posteriores.

**Archivo del modelo**: `app/Models/ProductionRecord.php`

**Ejemplo**: Un proceso "Fileteado" puede tener hijos "Envasado al vacío" y "Envasado en conserva", cada uno produciendo diferentes productos finales.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `production_records`

**Migración**: `database/migrations/companies/2025_11_23_135213_create_production_records_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del proceso |
| `production_id` | bigint | NO | FK a `productions` - Lote al que pertenece |
| `parent_record_id` | bigint | YES | FK a `production_records` - Proceso padre (árbol) |
| `process_id` | bigint | NO | FK a `processes` - Tipo de proceso maestro |
| `started_at` | timestamp | YES | Fecha/hora de inicio del proceso |
| `finished_at` | timestamp | YES | Fecha/hora de finalización del proceso |
| `notes` | text | YES | Notas adicionales del proceso |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `production_id` (index)
- `parent_record_id` (index)

**Constraints**:
- `production_id` → `productions.id` (onDelete: cascade)
- `parent_record_id` → `production_records.id` (onDelete: cascade)
- `process_id` → `processes.id` (onDelete: set null)

**Nota**: `process_id` tiene `onDelete: set null`, por lo que si se elimina un proceso maestro, el registro mantiene la referencia pero el proceso queda null.

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'production_id',
    'parent_record_id',
    'process_id',
    'started_at',
    'finished_at',
    'notes',
];
```

### Casts

```php
protected $casts = [
    'started_at' => 'datetime',
    'finished_at' => 'datetime',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `production()` - Lote de Producción
```php
public function production()
{
    return $this->belongsTo(Production::class, 'production_id');
}
```
- Relación muchos-a-uno con `Production`
- Cada proceso pertenece a un lote

### 2. `parent()` - Proceso Padre
```php
public function parent()
{
    return $this->belongsTo(ProductionRecord::class, 'parent_record_id');
}
```
- Relación muchos-a-uno consigo mismo (auto-referencia)
- Permite construir estructuras jerárquicas

### 3. `children()` - Procesos Hijos
```php
public function children()
{
    return $this->hasMany(ProductionRecord::class, 'parent_record_id');
}
```
- Relación uno-a-muchos consigo mismo
- Retorna todos los subprocesos directos

### 4. `process()` - Tipo de Proceso
```php
public function process()
{
    return $this->belongsTo(Process::class, 'process_id');
}
```
- Relación muchos-a-uno con `Process` (maestro de procesos)
- Define qué tipo de proceso es (ej: "Fileteado", "Envasado")

### 5. `inputs()` - Entradas del Proceso
```php
public function inputs()
{
    return $this->hasMany(ProductionInput::class, 'production_record_id');
}
```
- Relación uno-a-muchos con `ProductionInput`
- Cajas consumidas en este proceso

### 6. `outputs()` - Salidas del Proceso
```php
public function outputs()
{
    return $this->hasMany(ProductionOutput::class, 'production_record_id');
}
```
- Relación uno-a-muchos con `ProductionOutput`
- Productos producidos en este proceso

---

## 🎯 Métodos de Estado y Validación

### `isRoot()` - Verificar si es Raíz
```php
public function isRoot()
{
    return $this->parent_record_id === null;
}
```
- Retorna `true` si el proceso no tiene padre (es raíz del árbol)
- Los procesos raíz son el inicio del flujo de producción

### `isFinal()` - Verificar si es Final
```php
public function isFinal()
{
    return $this->inputs()->count() === 0 && $this->outputs()->count() > 0;
}
```
- Retorna `true` si el proceso solo produce outputs pero no consume inputs
- **Nota**: Un proceso raíz puede ser final si no tiene inputs propios (recibe de procesos padre)

### `isCompleted()` - Verificar si está Completado
```php
public function isCompleted()
{
    return $this->finished_at !== null;
}
```
- Retorna `true` si el proceso tiene `finished_at` establecido

---

## 🧮 Métodos de Cálculo

### Attributes Calculados

#### `getTotalInputWeightAttribute()` - Peso Total de Entradas
```php
public function getTotalInputWeightAttribute()
{
    return $this->inputs()
        ->with('box')
        ->get()
        ->sum(function ($input) {
            return $input->box->net_weight ?? 0;
        });
}
```
- Suma el `net_weight` de todas las cajas en inputs
- **Problema**: Ejecuta query y carga relaciones en cada acceso
- **Mejora sugerida**: Cachear o usar eager loading

#### `getTotalOutputWeightAttribute()` - Peso Total de Salidas
```php
public function getTotalOutputWeightAttribute()
{
    return $this->outputs()->sum('weight_kg');
}
```
- Suma el `weight_kg` de todos los outputs

#### `getTotalInputBoxesAttribute()` - Número de Cajas de Entrada
```php
public function getTotalInputBoxesAttribute()
{
    return $this->inputs()->count();
}
```
- Cuenta todas las entradas

#### `getTotalOutputBoxesAttribute()` - Número de Cajas de Salida
```php
public function getTotalOutputBoxesAttribute()
{
    return $this->outputs()->sum('boxes');
}
```
- Suma la cantidad de cajas declaradas en outputs

### Métodos de Árbol

#### `buildTree()` - Construir Árbol Recursivamente
```php
public function buildTree()
{
    $this->load('children.process', 'inputs.box.product', 'outputs.product');
    
    foreach ($this->children as $child) {
        $child->buildTree();
    }
    
    return $this;
}
```
- Carga relaciones de hijos y construye árbol recursivamente
- Eager loading de `process`, `inputs.box.product`, `outputs.product`
- Modifica la instancia actual (no retorna nueva)

#### `calculateNodeTotals()` - Calcular Totales del Nodo
```php
public function calculateNodeTotals()
{
    $inputWeight = $this->total_input_weight;
    $outputWeight = $this->total_output_weight;
    $difference = $inputWeight - $outputWeight;
    
    // Si hay pérdida (input > output)
    if ($difference > 0) {
        $waste = $difference;
        $wastePercentage = ($waste / $inputWeight) * 100;
        $yield = 0;
        $yieldPercentage = 0;
    }
    // Si hay ganancia (input < output)
    elseif ($difference < 0) {
        $waste = 0;
        $wastePercentage = 0;
        $yield = abs($difference); // output - input
        $yieldPercentage = ($yield / $inputWeight) * 100;
    }
    // Si es neutro (input = output)
    else {
        $waste = 0;
        $wastePercentage = 0;
        $yield = 0;
        $yieldPercentage = 0;
    }
    
    return [
        'inputWeight' => $inputWeight,
        'outputWeight' => $outputWeight,
        'waste' => $waste,
        'wastePercentage' => round($wastePercentage, 2),
        'yield' => round($yield, 2),
        'yieldPercentage' => round($yieldPercentage, 2),
        'inputBoxes' => $this->total_input_boxes,
        'outputBoxes' => $this->total_output_boxes,
    ];
}
```
- Calcula merma o rendimiento según corresponda del nodo específico
- **Lógica condicional**:
  - **Si hay pérdida** (input > output): `waste > 0`, `yield = 0`
  - **Si hay ganancia** (input < output): `yield > 0`, `waste = 0`
  - **Si es neutro** (input = output): ambos en `0`
- **Merma** (`waste`): Diferencia en kg cuando hay pérdida (inputWeight - outputWeight)
- **Porcentaje de Merma** (`wastePercentage`): Porcentaje de pérdida respecto al peso de entrada
- **Rendimiento** (`yield`): Diferencia en kg cuando hay ganancia (outputWeight - inputWeight)
- **Porcentaje de Rendimiento** (`yieldPercentage`): Porcentaje de ganancia respecto al peso de entrada
- No incluye datos de procesos hijos

#### `getNodeData()` - Obtener Estructura del Nodo para Diagrama
```php
public function getNodeData()
{
    // Asegurar que las relaciones estén cargadas
    $this->loadMissing(['process', 'inputs.box.product', 'outputs.product', 'children']);
    
    // Construir árbol de hijos recursivamente
    $childrenData = [];
    foreach ($this->children as $child) {
        $childrenData[] = $child->getNodeData();
    }
    
    // Preparar inputs...
    // Preparar outputs...
    // Calcular totales...
    
    return [
        'id' => $this->id,
        'production_id' => $this->production_id,
        'parent_record_id' => $this->parent_record_id,
        'process' => [...],
        'started_at' => $this->started_at?->toIso8601String(),
        'finished_at' => $this->finished_at?->toIso8601String(),
        'notes' => $this->notes,
        'isRoot' => $this->isRoot(),
        'isFinal' => $this->isFinal(),
        'isCompleted' => $this->isCompleted(),
        'inputs' => [...],
        'outputs' => [...],
        'children' => $childrenData,
        'totals' => $this->calculateNodeTotals(),
    ];
}
```
- Retorna estructura completa del nodo compatible con diagrama antiguo
- Incluye árbol de hijos recursivamente
- Formato usado por `Production::getDiagramData()`

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/ProductionRecordController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Procesos
```php
GET /v2/production-records
```

**Parámetros de query**:
- `production_id` (integer): Filtrar por lote de producción
- `parent_record_id` (integer): Filtrar por proceso padre
- `root_only` (boolean): Solo procesos raíz (`parent_record_id IS NULL`)
- `process_id` (integer): Filtrar por tipo de proceso
- `completed` (boolean): Filtrar por estado (true = finalizados, false = pendientes)
- `perPage` (integer, default: 15): Cantidad por página

**Relaciones cargadas**: `production`, `parent`, `process`, `inputs.box.product`, `outputs.product`

**Ordenamiento**: Por `started_at` descendente

#### `store(Request $request)` - Crear Proceso
```php
POST /v2/production-records
```

**Validación**:
```php
[
    'production_id' => 'required|exists:tenant.productions,id',
    'parent_record_id' => 'nullable|exists:tenant.production_records,id',
    'process_id' => 'required|exists:tenant.processes,id',
    'started_at' => 'nullable|date',
    'finished_at' => 'nullable|date',
    'notes' => 'nullable|string',
]
```

**Comportamiento**:
- Crea el proceso
- Carga relaciones para respuesta

**⚠️ Problema**: No valida si `parent_record_id` pertenece al mismo `production_id` (ver observaciones)

#### `show(string $id)` - Mostrar Proceso
```php
GET /v2/production-records/{id}
```

**Relaciones cargadas**: `production`, `parent`, `children`, `process`, `inputs.box.product`, `outputs.product`

#### `update(Request $request, string $id)` - Actualizar Proceso
```php
PUT /v2/production-records/{id}
```

**Validación**: Misma que `store()` pero con `sometimes` en lugar de `required`

**⚠️ Problema**: Permite cambiar `production_id`, lo que podría romper la integridad (ver observaciones)

#### `destroy(string $id)` - Eliminar Proceso
```php
DELETE /v2/production-records/{id}
```

**Comportamiento**: Elimina el proceso y todas sus relaciones (cascade de inputs/outputs, hijos)

**⚠️ Problema**: No valida si tiene inputs/outputs antes de eliminar (ver observaciones)

#### `tree(string $id)` - Obtener Árbol del Proceso
```php
GET /v2/production-records/{id}/tree
```

**Comportamiento**:
- Carga proceso con relaciones
- Llama a `buildTree()` para construir árbol completo
- Retorna estructura completa del árbol

#### `finish(string $id)` - Finalizar Proceso
```php
POST /v2/production-records/{id}/finish
```

**Validación**: Verifica que no esté ya finalizado

**Comportamiento**:
- Establece `finished_at` = now()
- Carga relaciones para respuesta

**⚠️ Problema**: No valida que tenga outputs antes de finalizar (ver observaciones)

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/ProductionRecordResource.php`

**Campos expuestos**:

### Ejemplo con Pérdida (Merma)

Cuando `totalInputWeight > totalOutputWeight`:

```json
{
    "id": 1,
    "productionId": 5,
    "production": {
        "id": 5,
        "lot": "LOT-2024-001",
        "openedAt": "2024-01-15T10:30:00Z",
        "closedAt": null
    },
    "parentRecordId": null,
    "parent": {
        "id": null,
        "process": null
    },
    "processId": 3,
    "process": {
        "id": 3,
        "name": "Fileteado",
        "type": "processing"
    },
    "startedAt": "2024-01-15T11:00:00Z",
    "finishedAt": null,
    "notes": "Proceso de fileteado manual",
    "isRoot": true,
    "isFinal": false,
    "isCompleted": false,
    "totalInputWeight": 150.50,
    "totalOutputWeight": 120.30,
    "totalInputBoxes": 5,
    "totalOutputBoxes": 8,
    "waste": 30.20,
    "wastePercentage": 20.07,
    "yield": 0,
    "yieldPercentage": 0,
    "inputs": [...],
    "outputs": [...],
    "children": [...],
    "createdAt": "2024-01-15T11:00:00Z",
    "updatedAt": "2024-01-15T11:00:00Z"
}
```

### Ejemplo con Ganancia (Rendimiento)

Cuando `totalInputWeight < totalOutputWeight`:

```json
{
    "id": 2,
    "productionId": 5,
    "processId": 4,
    "process": {
        "id": 4,
        "name": "Envasado en salmuera",
        "type": "packaging"
    },
    "totalInputWeight": 100.00,
    "totalOutputWeight": 120.00,
    "totalInputBoxes": 3,
    "totalOutputBoxes": 5,
    "waste": 0,
    "wastePercentage": 0,
    "yield": 20.00,
    "yieldPercentage": 20.00,
    "inputs": [...],
    "outputs": [...]
}
```

### Campos de Cálculo

**Lógica condicional**:
- **Si hay pérdida** (`totalInputWeight > totalOutputWeight`):
  - `waste` (number): Merma en kilogramos (totalInputWeight - totalOutputWeight)
  - `wastePercentage` (number): Porcentaje de merma respecto al peso de entrada (0-100)
  - `yield`: 0
  - `yieldPercentage`: 0

- **Si hay ganancia** (`totalInputWeight < totalOutputWeight`):
  - `waste`: 0
  - `wastePercentage`: 0
  - `yield` (number): Rendimiento en kilogramos (totalOutputWeight - totalInputWeight)
  - `yieldPercentage` (number): Porcentaje de ganancia respecto al peso de entrada (0-100)

- **Si es neutro** (`totalInputWeight = totalOutputWeight`):
  - Todos los campos de cálculo en `0`

**Nota**: Estos campos se calculan automáticamente en cada respuesta de la API y están disponibles en todos los endpoints que retornan `ProductionRecordResource` (index, show, store, update, tree, finish, etc.).

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/production-records/*`

---

## 📝 Ejemplos de Uso

### Crear Proceso Raíz
```http
POST /v2/production-records
Content-Type: application/json
X-Tenant: empresa1

{
    "production_id": 5,
    "process_id": 3,
    "started_at": "2024-01-15T11:00:00Z",
    "notes": "Proceso inicial de recepción"
}
```

### Crear Proceso Hijo
```http
POST /v2/production-records
Content-Type: application/json

{
    "production_id": 5,
    "parent_record_id": 1,
    "process_id": 4,
    "started_at": "2024-01-15T12:00:00Z"
}
```

### Obtener Árbol Completo
```http
GET /v2/production-records/1/tree
```

### Finalizar Proceso
```http
POST /v2/production-records/1/finish
```

### Filtrar Procesos de un Lote
```http
GET /v2/production-records?production_id=5&root_only=true
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Validaciones Faltantes en Creación/Actualización

1. **No Validar Ciclos en Árbol** (`app/Http/Controllers/v2/ProductionRecordController.php:61-81`)
   - No valida si `parent_record_id` crea un ciclo (ej: A es padre de B, B es padre de A)
   - **Líneas**: 61-81
   - **Problema**: Puede crear estructuras inválidas que causen loops infinitos
   - **Recomendación**: Agregar validación que recorra el árbol hacia arriba y verifique que no se cree ciclo

2. **No Validar Mismo Production** (`app/Http/Controllers/v2/ProductionRecordController.php:61-81`)
   - No valida si `parent_record_id` pertenece al mismo `production_id`
   - **Líneas**: 63-70
   - **Problema**: Un proceso puede tener padre de otro lote, rompiendo la integridad
   - **Recomendación**: Agregar validación:
     ```php
     if ($validated['parent_record_id']) {
         $parent = ProductionRecord::findOrFail($validated['parent_record_id']);
         if ($parent->production_id !== $validated['production_id']) {
             return error('Parent must belong to same production');
         }
     }
     ```

3. **Permite Cambiar Production_id** (`app/Http/Controllers/v2/ProductionRecordController.php:106-128`)
   - `update()` permite cambiar `production_id`
   - **Líneas**: 110
   - **Problema**: Puede mover un proceso a otro lote, rompiendo relaciones
   - **Recomendación**: 
     - No permitir cambiar `production_id` en update
     - O validar que no tenga inputs/outputs antes de permitir cambio

### ⚠️ Eliminación sin Validaciones

4. **Eliminación sin Validar Inputs/Outputs** (`app/Http/Controllers/v2/ProductionRecordController.php:133-141`)
   - `destroy()` no valida si tiene inputs/outputs
   - **Líneas**: 133-141
   - **Problema**: Puede eliminar procesos con datos asociados, aunque cascade lo maneje, podría ser preferible soft delete
   - **Recomendación**: 
     - Validar antes de eliminar
     - O implementar soft deletes para permitir recuperación

5. **Eliminación de Raíz Elimina Todo** (`app/Models/ProductionRecord.php`)
   - Si se elimina un proceso raíz, cascade elimina todos los hijos
   - **Problema**: Puede ser accidental y perder mucha información
   - **Recomendación**: 
     - Validar explícitamente antes de eliminar raíz
     - O preguntar confirmación
     - O implementar soft deletes

### ⚠️ Finalización sin Validaciones

6. **Finalizar sin Outputs** (`app/Http/Controllers/v2/ProductionRecordController.php:167-185`)
   - `finish()` no valida que tenga outputs antes de finalizar
   - **Líneas**: 167-185
   - **Problema**: Pueden finalizarse procesos sin producción registrada
   - **Recomendación**: Validar que tenga al menos un output antes de finalizar

7. **Finalizar con Procesos Hijos Pendientes** (`app/Http/Controllers/v2/ProductionRecordController.php:167-185`)
   - No valida si tiene procesos hijos sin finalizar
   - **Líneas**: 167-185
   - **Problema**: Puede finalizarse proceso padre antes que hijos
   - **Recomendación**: Validar que todos los hijos estén finalizados antes de finalizar padre

### ⚠️ Performance: Queries N+1 en Attributes

8. **Attributes Calculados con Queries** (`app/Models/ProductionRecord.php:95-127`)
   - `total_input_weight`, `total_input_boxes`, etc. ejecutan queries en cada acceso
   - **Líneas**: 95-103, 108-111, 116-119, 124-127
   - **Problema**: Si se accede múltiples veces, se ejecutan múltiples queries
   - **Recomendación**: 
     - Cachear resultados
     - O usar eager loading agregado en controlador
     - O calcular una vez y almacenar

9. **BuildTree con Eager Loading Ineficiente** (`app/Models/ProductionRecord.php:140-149`)
   - `buildTree()` carga relaciones en cada nivel recursivamente
   - **Líneas**: 140-149
   - **Problema**: Puede haber múltiples queries si el árbol es profundo
   - **Recomendación**: 
     - Cargar todas las relaciones de una vez antes de recursión
     - O usar eager loading más profundo

### ⚠️ Lógica de isFinal() Potencialmente Incorrecta

10. **isFinal() No Considera Procesos Hijos** (`app/Models/ProductionRecord.php:87-90`)
    - `isFinal()` solo verifica inputs propios, no si tiene hijos
    - **Líneas**: 87-90
    - **Problema**: Un proceso con hijos no debería considerarse "final"
    - **Recomendación**: Agregar validación de que no tenga hijos:
      ```php
      return $this->inputs()->count() === 0 
          && $this->outputs()->count() > 0
          && $this->children()->count() === 0;
      ```

### ⚠️ Manejo de División por Cero

11. **calculateNodeTotals() Valida División por Cero** (`app/Models/ProductionRecord.php:154-169`)
    - Valida correctamente división por cero en porcentaje
    - **Líneas**: 159
    - **Estado**: Correcto, pero podría validar también si inputWeight < 0

### ⚠️ Falta de Validación de Estado del Lote

12. **No Validar Lote Abierto** (`app/Http/Controllers/v2/ProductionRecordController.php:61-81`)
    - No valida si el lote está abierto antes de crear/actualizar procesos
    - **Líneas**: 61-81, 106-128
    - **Problema**: Pueden crearse procesos en lotes cerrados
    - **Recomendación**: Agregar validación `$production->isOpen()`

### ⚠️ Process_id Puede Quedar Null

13. **onDelete: set null en Process** (`database/migrations/companies/2025_11_23_135213_create_production_records_table.php:18`)
    - Si se elimina un `Process`, `process_id` queda null
    - **Problema**: El registro queda sin tipo de proceso
    - **Recomendación**: 
      - Prevenir eliminación de Process si tiene ProductionRecords
      - O usar onDelete: restrict

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

