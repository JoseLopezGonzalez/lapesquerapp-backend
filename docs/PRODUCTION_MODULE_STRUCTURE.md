# Estructura del Módulo de Producción (Versión Definitiva)

## 📋 Índice
1. [Concepto General](#concepto-general)
2. [Entidades del Sistema](#entidades-del-sistema)
3. [Estructura de Base de Datos](#estructura-de-base-de-datos)
4. [Modelos Eloquent](#modelos-eloquent)
5. [Lógica de Negocio](#lógica-de-negocio)
6. [API y Controladores](#api-y-controladores)
7. [Migración Gradual](#migración-gradual)

---

## 🎯 Concepto General

### Principios Fundamentales

1. **Eliminación del JSON como fuente de verdad**
   - El sistema anterior almacenaba toda la estructura del diagrama en `diagram_data` (JSON)
   - La nueva estructura usa entidades relacionales: `production_records`, `production_inputs`, `production_outputs`
   - El diagrama se calcula **dinámicamente** a partir de los procesos almacenados

2. **Unidad mínima: CAJA (box)**
   - Toda la producción se rastrea a nivel de caja individual
   - Los palets son contenedores físicos de cajas
   - La trazabilidad es total por caja

3. **Producción desacoplada del Stock**
   - La producción declara **salidas lógicas** (qué se produjo)
   - El stock registra **palets reales** y **cajas reales**
   - La conciliación compara ambos para validar

---

## 🗄️ Entidades del Sistema

### Entidades Existentes (Sin Cambios)

#### `pallets`
Representa cada palet físico en el almacén.

**Campos principales:**
- `id`
- `observations`
- `state_id`
- `timestamps`

#### `boxes`
Representa cada caja individual del sistema.

**Campos principales:**
- `id`
- `article_id` (FK a products)
- `lot` (string)
- `gs1_128`
- `gross_weight`
- `net_weight`
- `timestamps`

**Nota:** El sistema obtiene automáticamente peso, producto, lote desde esta entidad.

#### `pallet_boxes`
Tabla de relación muchos-a-muchos entre palets y cajas.

**Campos:**
- `id`
- `pallet_id` (FK a pallets)
- `box_id` (FK a boxes)
- `timestamps`

---

### Nuevas Entidades

#### 1. `productions` (Cabecera del Lote)

**Descripción:** Representa el lote de producción completo. Es la cabecera que agrupa todos los procesos.

**Campos:**
- `id` - Identificador único
- `lot` - Número de lote (string, nullable)
- `species_id` - Especie (FK a species, nullable - opcional)
- `capture_zone_id` - Zona de captura (FK a capture_zones, nullable - mantenido para migración)
- `date` - Fecha (date, nullable - mantenido para migración)
- `notes` - Notas adicionales (text, nullable)
- `diagram_data` - JSON del diagrama antiguo (json, nullable - mantenido para migración gradual)
- `opened_at` - Timestamp de apertura del lote (timestamp, nullable) ⭐ **NUEVO**
- `closed_at` - Timestamp de cierre del lote (timestamp, nullable) ⭐ **NUEVO**
- `timestamps` - created_at, updated_at

**Relaciones:**
- `hasMany(ProductionRecord::class)` - Todos los procesos del lote
- `belongsTo(Species::class)` - Especie (opcional)
- `belongsTo(CaptureZone::class)` - Zona de captura (legacy)

**Notas:**
- Los campos `capture_zone_id`, `date`, `diagram_data` se mantienen para facilitar la migración gradual del frontend
- `species_id` es opcional según la nueva especificación
- `opened_at` y `closed_at` controlan el ciclo de vida del lote

---

#### 2. `production_records` (Procesos dentro del lote)

**Descripción:** Cada proceso real dentro de un lote de producción. El árbol se construye mediante relaciones padre-hijo.

**Campos:**
- `id` - Identificador único
- `production_id` - FK a productions (requerido)
- `parent_record_id` - FK a production_records (nullable) ⭐ **Clave para el árbol**
- `process_id` - FK a processes (nullable) - Tipo de proceso (starting, process, final)
- `started_at` - Timestamp de inicio del proceso (timestamp, nullable)
- `finished_at` - Timestamp de finalización del proceso (timestamp, nullable)
- `notes` - Notas del proceso (text, nullable)
- `timestamps` - created_at, updated_at

**Lógica del Árbol:**
- **Proceso raíz:** `parent_record_id = null` - Consume cajas directamente de palets
- **Procesos intermedios:** Tienen `parent_record_id` apuntando al proceso padre
- **Proceso final:** Tiene `parent_record_id` y solo tiene outputs (salida lógica)

**Relaciones:**
- `belongsTo(Production::class)` - Lote al que pertenece
- `belongsTo(ProductionRecord::class, 'parent_record_id')` - Proceso padre
- `hasMany(ProductionRecord::class, 'parent_record_id')` - Procesos hijos
- `hasMany(ProductionInput::class)` - Entradas (cajas consumidas)
- `hasMany(ProductionOutput::class)` - Salidas (productos producidos)
- `belongsTo(Process::class)` - Tipo de proceso

**Índices:**
- `production_id` - Para búsquedas por lote
- `parent_record_id` - Para construcción del árbol

---

#### 3. `production_inputs` (Entradas del Proceso)

**Descripción:** Registra las cajas que entran a un proceso. La unidad mínima es la caja.

**Campos:**
- `id` - Identificador único
- `production_record_id` - FK a production_records (requerido)
- `box_id` - FK a boxes (requerido)
- `timestamps` - created_at, updated_at

**Características:**
- **NO guarda peso, lote ni producto** - Todo se obtiene automáticamente desde `boxes`
- El sistema calcula automáticamente:
  - Peso total: suma de `boxes.net_weight`
  - Producto: desde `box.product`
  - Lote: desde `box.lot`
  - Palet: desde `box.pallet` (relación a través de pallet_boxes)

**Relaciones:**
- `belongsTo(ProductionRecord::class)` - Proceso al que pertenece
- `belongsTo(Box::class)` - Caja individual

**Constraints:**
- **UNIQUE(`production_record_id`, `box_id`)** - Una caja no puede estar dos veces en el mismo proceso

**Índices:**
- `production_record_id` - Para búsquedas por proceso
- `box_id` - Para búsquedas por caja

---

#### 4. `production_outputs` (Salidas del Proceso)

**Descripción:** Registra la salida lógica del proceso (cantidad producida). **NO crea palets automáticamente.**

**Campos:**
- `id` - Identificador único
- `production_record_id` - FK a production_records (requerido)
- `product_id` - FK a products (requerido) - Producto producido
- `lot_id` - Lote del producto producido (string, nullable)
- `boxes` - Cantidad de cajas producidas (integer, default: 0)
- `weight_kg` - Peso total producido en kilogramos (decimal 10,2, default: 0)
- `timestamps` - created_at, updated_at

**Características:**
- Registra la **salida lógica** del proceso
- El operario debe registrar los palets **manualmente** en el módulo de stock
- No hay creación automática de palets ni cajas desde aquí

**Relaciones:**
- `belongsTo(ProductionRecord::class)` - Proceso que generó la salida
- `belongsTo(Product::class)` - Producto producido

**Índices:**
- `production_record_id` - Para búsquedas por proceso
- `product_id` - Para búsquedas por producto
- `lot_id` - Para búsquedas por lote

---

## 🔄 Flujo de Datos

### 1. Creación de un Lote de Producción

```
1. Se crea un registro en `productions`
   - lot, species_id (opcional), notes
   - opened_at = now()

2. Se crean procesos raíz en `production_records`
   - production_id = id del lote
   - parent_record_id = null
   - started_at = now()

3. Se registran entradas en `production_inputs`
   - production_record_id = id del proceso raíz
   - box_id = id de cada caja consumida
```

### 2. Procesos Intermedios

```
1. Se crea proceso hijo en `production_records`
   - production_id = id del lote
   - parent_record_id = id del proceso padre
   - started_at = now()

2. Las entradas pueden venir de:
   - Cajas de palets (si es proceso raíz)
   - Salidas de procesos anteriores (lógica del árbol)

3. Se registran salidas en `production_outputs`
   - production_record_id = id del proceso
   - product_id, lot_id, boxes, weight_kg
```

### 3. Proceso Final

```
1. Proceso con parent_record_id (tiene padre)
2. Solo tiene outputs (production_outputs)
3. No tiene inputs directos (usa salidas de procesos anteriores)
4. finished_at = now() cuando se completa
```

### 4. Cierre del Lote

```
1. Se actualiza `productions.closed_at = now()`
2. Todos los procesos deben estar finished_at != null
3. Se realiza conciliación producción ↔ stock
```

---

## 🔍 Conciliación Producción ↔ Stock

### Concepto

La producción declara **salidas lógicas** (qué se produjo según los procesos).
El stock registra **palets reales** y **cajas reales** (qué hay físicamente en almacén).

### Proceso de Conciliación

1. **Obtener salidas declaradas:**
   - Sumar `production_outputs.boxes` por producto y lote
   - Sumar `production_outputs.weight_kg` por producto y lote

2. **Obtener stock real:**
   - Consultar `pallet_boxes` → `boxes` → filtrar por lote de producción
   - Contar cajas y sumar pesos desde `boxes.net_weight`

3. **Comparar:**
   - **Verde:** Coincide (diferencia < 1% o umbral configurable)
   - **Amarillo:** Diferencia leve (1% - 5%)
   - **Rojo:** Diferencia importante (> 5%)

4. **Recomendación:**
   - Bloquear el cierre de producción si no está conciliado (rojo)

### Ejemplo de Cálculo

```php
// Salidas declaradas
$declaredBoxes = ProductionOutput::where('production_record_id', $recordId)
    ->sum('boxes');
$declaredWeight = ProductionOutput::where('production_record_id', $recordId)
    ->sum('weight_kg');

// Stock real (cajas del lote en palets)
$realBoxes = Box::where('lot', $production->lot)
    ->whereHas('palletBox')
    ->count();
$realWeight = Box::where('lot', $production->lot)
    ->whereHas('palletBox')
    ->sum('net_weight');

// Comparación
$boxDifference = abs($declaredBoxes - $realBoxes);
$weightDifference = abs($declaredWeight - $realWeight);
```

---

## 🌳 Construcción del Árbol de Procesos

### Algoritmo

1. **Obtener procesos raíz:**
   ```php
   $rootRecords = ProductionRecord::where('production_id', $productionId)
       ->whereNull('parent_record_id')
       ->get();
   ```

2. **Construir árbol recursivamente:**
   ```php
   function buildTree($parentId = null) {
       $records = ProductionRecord::where('parent_record_id', $parentId)->get();
       foreach ($records as $record) {
           $record->children = buildTree($record->id);
       }
       return $records;
   }
   ```

3. **Validaciones:**
   - Proceso raíz: `parent_record_id = null` y tiene inputs de cajas
   - Proceso intermedio: tiene padre y puede tener inputs/outputs
   - Proceso final: tiene padre y solo tiene outputs

---

## 📊 Cálculo Dinámico del Diagrama

### Generación desde Procesos

El diagrama se genera dinámicamente leyendo:
1. `production_records` - Estructura del árbol
2. `production_inputs` - Entradas por proceso
3. `production_outputs` - Salidas por proceso
4. `boxes` - Datos de cajas (peso, producto, lote)

### Estructura del Diagrama Generado

```json
{
  "processNodes": [
    {
      "id": "record_id",
      "process": {
        "id": "process_id",
        "name": "Nombre del proceso"
      },
      "inputs": [
        {
          "box_id": 1,
          "product": {...},
          "weight": 10.5,
          "lot": "LOT001"
        }
      ],
      "outputs": [
        {
          "product_id": 2,
          "product": {...},
          "boxes": 50,
          "weight_kg": 500.0,
          "lot_id": "LOT002"
        }
      ],
      "parent_id": null,
      "children": [...]
    }
  ],
  "totals": {
    "totalInputWeight": 1000.0,
    "totalOutputWeight": 950.0,
    "totalProfit": 5000.0
  }
}
```

---

## 🚫 Eliminación de Nodos de Distribución

### Cambio Importante

**No existe nodo de distribución** en la nueva estructura.

La distribución se calcula automáticamente:
- Leyendo ventas del lote desde el módulo de órdenes
- Generando nodos virtuales en el diagrama si se desea visualizar

---

## 📝 Notas de Implementación

### Migración Gradual

1. **Fase 1:** Crear nuevas tablas sin eliminar campos antiguos
2. **Fase 2:** Implementar nuevos endpoints paralelos a los antiguos
3. **Fase 3:** Migrar frontend para usar nuevos endpoints
4. **Fase 4:** Deprecar endpoints antiguos y campos legacy

### Campos Legacy Mantenidos

- `productions.diagram_data` - Para compatibilidad durante migración
- `productions.capture_zone_id` - Para compatibilidad durante migración
- `productions.date` - Para compatibilidad durante migración

Estos campos se pueden eliminar en una migración futura cuando el frontend esté completamente migrado.

---

## 🔄 Estado de Implementación

### ✅ Completado
- [x] Migraciones de base de datos
  - [x] `production_records`
  - [x] `production_inputs`
  - [x] `production_outputs`
  - [x] Actualización de `productions` (agregar opened_at/closed_at)
- [x] Modelos Eloquent
  - [x] `ProductionRecord` - Con relaciones, métodos de árbol, cálculos
  - [x] `ProductionInput` - Con acceso a datos de caja
  - [x] `ProductionOutput` - Con cálculos de peso promedio
  - [x] Actualizar `Production` - Nuevas relaciones, métodos de estado, conciliación
  - [x] Actualizar `Box` - Relación con production_inputs para trazabilidad

### ✅ Rutas API v2 Implementadas

Todas las rutas están bajo el prefijo `/v2` y requieren autenticación (`auth:sanctum`) y roles (`superuser,manager,admin,store_operator`).

#### Production (Lotes)
- `GET /v2/productions` - Listar producciones
- `POST /v2/productions` - Crear producción
- `GET /v2/productions/{id}` - Mostrar producción
- `PUT /v2/productions/{id}` - Actualizar producción
- `DELETE /v2/productions/{id}` - Eliminar producción
- `GET /v2/productions/{id}/diagram` - Obtener diagrama calculado
- `GET /v2/productions/{id}/process-tree` - Obtener árbol de procesos
- `GET /v2/productions/{id}/totals` - Obtener totales globales
- `GET /v2/productions/{id}/reconciliation` - Obtener conciliación

#### Production Records (Procesos)
- `GET /v2/production-records` - Listar procesos
- `POST /v2/production-records` - Crear proceso
- `GET /v2/production-records/{id}` - Mostrar proceso
- `PUT /v2/production-records/{id}` - Actualizar proceso
- `DELETE /v2/production-records/{id}` - Eliminar proceso
- `GET /v2/production-records/{id}/tree` - Obtener árbol del proceso
- `POST /v2/production-records/{id}/finish` - Finalizar proceso

#### Production Inputs (Entradas)
- `GET /v2/production-inputs` - Listar entradas (sin paginación, devuelve todos los resultados)
  - Parámetros de query:
    - `production_record_id` - Filtrar por record de producción
    - `box_id` - Filtrar por caja específica
    - `production_id` - Filtrar por producción (a través del record)
- `POST /v2/production-inputs` - Crear entrada
- `POST /v2/production-inputs/multiple` - Crear múltiples entradas
- `GET /v2/production-inputs/{id}` - Mostrar entrada
- `DELETE /v2/production-inputs/{id}` - Eliminar entrada

#### Production Outputs (Salidas)
- `GET /v2/production-outputs` - Listar salidas
- `POST /v2/production-outputs` - Crear salida
- `GET /v2/production-outputs/{id}` - Mostrar salida
- `PUT /v2/production-outputs/{id}` - Actualizar salida
- `DELETE /v2/production-outputs/{id}` - Eliminar salida

### 🚧 Pendiente
- [ ] Validaciones adicionales
  - [ ] Validar estructura del árbol
  - [ ] Validar cajas disponibles antes de asignar
  - [ ] Validar conciliación antes de cerrar

### ✅ Implementado
- [x] Controladores y API v2
  - [x] `ProductionRecordController` - CRUD completo + tree() + finish()
  - [x] `ProductionInputController` - CRUD + storeMultiple()
  - [x] `ProductionOutputController` - CRUD completo
  - [x] `ProductionController` v2 - getDiagram(), getProcessTree(), getTotals(), getReconciliation()
- [x] Lógica de negocio
  - [x] Construcción del árbol de procesos (recursivo)
  - [x] Cálculo dinámico del diagrama (`calculateDiagram()`, `getDiagramData()`)
  - [x] Cálculo de totales por nodo (`calculateNodeTotals()`)
  - [x] Cálculo de totales globales (`calculateGlobalTotals()`)
  - [x] Estructura del nodo para diagrama (`getNodeData()`)
  - [x] Conciliación producción ↔ stock (`reconcile()`)
- [ ] Validaciones
  - [ ] Validar estructura del árbol
  - [ ] Validar cajas disponibles
  - [ ] Validar conciliación antes de cerrar

---

## 🏗️ Modelos Eloquent

### ProductionRecord

**Ubicación:** `app/Models/ProductionRecord.php`

**Relaciones principales:**
- `production()` - BelongsTo Production
- `parent()` - BelongsTo ProductionRecord (proceso padre)
- `children()` - HasMany ProductionRecord (procesos hijos)
- `process()` - BelongsTo Process
- `inputs()` - HasMany ProductionInput
- `outputs()` - HasMany ProductionOutput

**Métodos útiles:**
- `isRoot()` - Verifica si es proceso raíz
- `isFinal()` - Verifica si es proceso final
- `isCompleted()` - Verifica si está completado
- `buildTree()` - Construye árbol recursivamente
- `total_input_weight` - Accessor: peso total de entradas
- `total_output_weight` - Accessor: peso total de salidas
- `total_input_boxes` - Accessor: número de cajas de entrada
- `total_output_boxes` - Accessor: número de cajas de salida

### ProductionInput

**Ubicación:** `app/Models/ProductionInput.php`

**Relaciones principales:**
- `productionRecord()` - BelongsTo ProductionRecord
- `box()` - BelongsTo Box

**Accessors (obtienen datos desde la caja):**
- `product` - Producto desde box.product
- `lot` - Lote desde box.lot
- `weight` - Peso desde box.net_weight
- `pallet` - Palet desde box.pallet

### ProductionOutput

**Ubicación:** `app/Models/ProductionOutput.php`

**Relaciones principales:**
- `productionRecord()` - BelongsTo ProductionRecord
- `product()` - BelongsTo Product

**Métodos útiles:**
- `average_weight_per_box` - Accessor: peso promedio por caja

### Production (Actualizado)

**Ubicación:** `app/Models/Production.php`

**Nuevas relaciones:**
- `records()` - HasMany ProductionRecord
- `rootRecords()` - HasMany ProductionRecord (solo raíces)
- `allInputs()` - Query builder para todos los inputs del lote
- `allOutputs()` - Query builder para todos los outputs del lote

**Nuevos métodos de estado:**
- `isOpen()` - Verifica si está abierto
- `isClosed()` - Verifica si está cerrado
- `open()` - Abre el lote
- `close()` - Cierra el lote

**Nuevos métodos de cálculo:**
- `buildProcessTree()` - Construye árbol completo de procesos
- `total_input_weight` - Accessor: peso total de entrada
- `total_output_weight` - Accessor: peso total de salida
- `total_input_boxes` - Accessor: número total de cajas entrada
- `total_output_boxes` - Accessor: número total de cajas salida
- `total_waste` - Accessor: merma total (entrada - salida)
- `waste_percentage` - Accessor: porcentaje de merma

**Métodos de conciliación:**
- `getStockBoxes()` - Obtiene cajas del lote en stock
- `stock_weight` - Accessor: peso total en stock
- `stock_boxes_count` - Accessor: número de cajas en stock
- `reconcile()` - Realiza conciliación y retorna estado (green/yellow/red)

### Box (Actualizado)

**Nueva relación:**
- `productionInputs()` - HasMany ProductionInput (trazabilidad)

---

## 📚 Referencias

- Especificación original del módulo de producción
- Modelos existentes: `Box`, `Pallet`, `PalletBox`, `Product`
- Tabla de procesos: `processes`

---

**Última actualización:** 2025-11-23
**Versión del documento:** 1.1

