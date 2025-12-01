# Producción - Consumos de Outputs del Padre (ProductionOutputConsumption)

## ⚠️ Estado de la API
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `ProductionOutputConsumption` representa el **consumo de una salida de producción del proceso padre** por parte de un proceso hijo. Esto permite que los procesos hijos no solo consuman cajas del stock, sino también parte o toda la salida del proceso padre.

**Archivo del modelo**: `app/Models/ProductionOutputConsumption.php`

**Concepto clave**: Cuando un proceso tiene un proceso padre, puede consumir tanto:
- **Cajas del stock** (a través de `ProductionInput`)
- **Salidas del proceso padre** (a través de `ProductionOutputConsumption` - NUEVO)

**Ejemplo**: 
- Proceso Padre "Fileteado" produce 300kg de filetes (ProductionOutput)
- Proceso Hijo "Envasado al vacío" puede consumir 150kg de esos filetes (ProductionOutputConsumption)

---

## 🗄️ Estructura de Base de Datos

### Tabla: `production_output_consumptions`

**Migración**: `database/migrations/companies/2025_12_01_220536_create_production_output_consumptions_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del consumo |
| `production_record_id` | bigint | NO | FK a `production_records` - Proceso hijo que consume |
| `production_output_id` | bigint | NO | FK a `production_outputs` - Output del padre que se consume |
| `consumed_weight_kg` | decimal(10,2) | NO | Peso consumido en kilogramos (default: 0) |
| `consumed_boxes` | integer | NO | Cantidad de cajas consumidas (default: 0) |
| `notes` | text | YES | Notas adicionales del consumo |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `production_record_id` (index)
- `production_output_id` (index)
- **Unique constraint**: `['production_record_id', 'production_output_id']` - Un proceso solo puede consumir un output una vez

**Constraints**:
- `production_record_id` → `production_records.id` (onDelete: cascade)
- `production_output_id` → `production_outputs.id` (onDelete: cascade)

**Nota importante**: El unique constraint asegura que un proceso solo pueda tener un registro de consumo por cada output del padre. Si necesita consumir más, debe actualizar el consumo existente.

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'production_record_id',
    'production_output_id',
    'consumed_weight_kg',
    'consumed_boxes',
    'notes',
];
```

### Casts

```php
protected $casts = [
    'consumed_weight_kg' => 'decimal:2',
    'consumed_boxes' => 'integer',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `productionRecord()` - Proceso que Consume

```php
public function productionRecord()
{
    return $this->belongsTo(ProductionRecord::class, 'production_record_id');
}
```

- Relación muchos-a-uno con `ProductionRecord`
- El proceso hijo que está consumiendo el output del padre

### 2. `productionOutput()` - Output del Padre Consumido

```php
public function productionOutput()
{
    return $this->belongsTo(ProductionOutput::class, 'production_output_id');
}
```

- Relación muchos-a-uno con `ProductionOutput`
- El output del proceso padre que se está consumiendo

---

## 🎯 Attributes Calculados (Accessors)

### `getProductAttribute()` - Producto desde el Output

```php
public function getProductAttribute()
{
    return $this->productionOutput->product ?? null;
}
```

- Retorna el producto desde el output consumido

### `getParentRecordAttribute()` - Proceso Padre

```php
public function getParentRecordAttribute()
{
    return $this->productionOutput->productionRecord ?? null;
}
```

- Retorna el proceso padre desde el output consumido

### `isComplete()` - Verificar si Consumo es Completo

```php
public function isComplete()
{
    $output = $this->productionOutput;
    if (!$output) {
        return false;
    }

    $weightComplete = $this->consumed_weight_kg >= $output->weight_kg;
    $boxesComplete = $this->consumed_boxes >= $output->boxes;

    return $weightComplete && $boxesComplete;
}
```

- Verifica si el consumo es completo (consume todo el output)

### `isPartial()` - Verificar si Consumo es Parcial

```php
public function isPartial()
{
    return !$this->isComplete();
}
```

- Verifica si el consumo es parcial

### `getWeightConsumptionPercentageAttribute()` - Porcentaje de Consumo por Peso

```php
public function getWeightConsumptionPercentageAttribute()
{
    $output = $this->productionOutput;
    if (!$output || $output->weight_kg <= 0) {
        return 0;
    }

    return ($this->consumed_weight_kg / $output->weight_kg) * 100;
}
```

- Calcula el porcentaje del output consumido por peso

### `getBoxesConsumptionPercentageAttribute()` - Porcentaje de Consumo por Cajas

```php
public function getBoxesConsumptionPercentageAttribute()
{
    $output = $this->productionOutput;
    if (!$output || $output->boxes <= 0) {
        return 0;
    }

    return ($this->consumed_boxes / $output->boxes) * 100;
}
```

- Calcula el porcentaje del output consumido por cajas

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/ProductionOutputConsumptionController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Consumos

```http
GET /v2/production-output-consumptions
```

**Parámetros de query**:
- `production_record_id` (integer): Filtrar por proceso que consume
- `production_output_id` (integer): Filtrar por output consumido
- `production_id` (integer): Filtrar por lote de producción
- `parent_record_id` (integer): Filtrar por procesos hijos de un proceso específico
- `perPage` (integer, default: 15): Cantidad por página

**Relaciones cargadas**: `productionRecord.process`, `productionOutput.product`

**Ordenamiento**: Por defecto (probablemente `created_at`)

#### `store(Request $request)` - Crear Consumo

```http
POST /v2/production-output-consumptions
```

**Validación**:
```php
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'production_output_id' => 'required|exists:tenant.production_outputs,id',
    'consumed_weight_kg' => 'required|numeric|min:0',
    'consumed_boxes' => 'nullable|integer|min:0',
    'notes' => 'nullable|string',
]
```

**Validaciones de negocio**:
1. El proceso debe tener un proceso padre
2. El output debe pertenecer al proceso padre directo
3. No puede existir ya un consumo del mismo output en el mismo proceso
4. El consumo no puede exceder el output disponible

**Comportamiento**:
- Crea el consumo
- Carga relaciones para respuesta

**Respuesta**: 201 con datos del consumo creado

#### `show(string $id)` - Mostrar Consumo

```http
GET /v2/production-output-consumptions/{id}
```

**Relaciones cargadas**: `productionRecord.process`, `productionOutput.product`

#### `update(Request $request, string $id)` - Actualizar Consumo

```http
PUT /v2/production-output-consumptions/{id}
```

**Validación**: Misma que `store()` pero con `sometimes` en lugar de `required`

**Validaciones de negocio**:
1. El consumo actualizado no puede exceder el output disponible

**Comportamiento**:
- Actualiza el consumo
- Carga relaciones para respuesta

#### `destroy(string $id)` - Eliminar Consumo

```http
DELETE /v2/production-output-consumptions/{id}
```

**Comportamiento**: Elimina el consumo (cascade no afecta, solo es referencia)

#### `getAvailableOutputs(string $productionRecordId)` - Outputs Disponibles

```http
GET /v2/production-output-consumptions/available-outputs/{productionRecordId}
```

**Comportamiento**: 
- Retorna los outputs del proceso padre disponibles para consumo
- Calcula cuánto está disponible considerando otros consumos existentes

**Respuesta**: Array de outputs con información de disponibilidad

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/ProductionOutputConsumptionResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "productionRecordId": 123,
    "productionOutputId": 456,
    "consumedWeightKg": 150.50,
    "consumedBoxes": 10,
    "notes": "Consumo parcial para envasado",
    "productionOutput": {
        "id": 456,
        "productId": 10,
        "product": {
            "id": 10,
            "name": "Filetes de atún"
        },
        "weightKg": 300.00,
        "boxes": 20
    },
    "product": {
        "id": 10,
        "name": "Filetes de atún"
    },
    "productionRecord": {
        "id": 123,
        "process": {
            "id": 5,
            "name": "Envasado al vacío"
        }
    },
    "parentRecord": {
        "id": 100,
        "process": {
            "id": 3,
            "name": "Fileteado"
        }
    },
    "isComplete": false,
    "isPartial": true,
    "weightConsumptionPercentage": 50.17,
    "boxesConsumptionPercentage": 50.00,
    "outputTotalWeight": 300.00,
    "outputTotalBoxes": 20,
    "outputAvailableWeight": 149.50,
    "outputAvailableBoxes": 10,
    "createdAt": "2024-01-15T12:00:00Z",
    "updatedAt": "2024-01-15T12:00:00Z"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/production-output-consumptions/*`

---

## 📝 Ejemplos de Uso

### Crear un Consumo de Output del Padre

```http
POST /v2/production-output-consumptions
Content-Type: application/json
X-Tenant: empresa1

{
    "production_record_id": 123,
    "production_output_id": 456,
    "consumed_weight_kg": 150.50,
    "consumed_boxes": 10,
    "notes": "Consumo parcial para envasado al vacío"
}
```

### Listar Consumos de un Proceso

```http
GET /v2/production-output-consumptions?production_record_id=123
```

### Obtener Outputs Disponibles para un Proceso Hijo

```http
GET /v2/production-output-consumptions/available-outputs/123
```

### Actualizar un Consumo

```http
PUT /v2/production-output-consumptions/1
Content-Type: application/json

{
    "consumed_weight_kg": 200.00,
    "consumed_boxes": 15
}
```

---

## 🔍 Conceptos Importantes

### Consumo Único por Output

Un proceso solo puede tener **un registro de consumo** por cada output del padre. Si necesita consumir más, debe actualizar el consumo existente.

**Ejemplo**:
- Output del padre: 300kg
- Consumo inicial: 150kg
- Para consumir más: Actualizar a 200kg (no crear otro registro)

### Validación de Disponibilidad

El sistema valida que el consumo no exceda el output disponible, considerando:
- El total del output
- Otros consumos existentes del mismo output

### Relación con ProductionInput

`ProductionOutputConsumption` es **complementario** a `ProductionInput`:
- **ProductionInput**: Consume cajas del stock
- **ProductionOutputConsumption**: Consume outputs del proceso padre

Un proceso hijo puede tener ambos tipos de inputs.

---

## 🔗 Integración con Otros Modelos

### ProductionRecord

El modelo `ProductionRecord` tiene:
- Relación `parentOutputConsumptions()` - Consumos de outputs del padre
- Método `getTotalInputWeightAttribute()` - Incluye consumos del padre en el cálculo
- Método `getAvailableParentOutputs()` - Outputs disponibles para consumo

### ProductionOutput

El modelo `ProductionOutput` tiene:
- Relación `consumptions()` - Consumos de este output
- Método `getAvailableWeightKgAttribute()` - Peso disponible
- Método `getAvailableBoxesAttribute()` - Cajas disponibles
- Método `isFullyConsumed()` - Verificar si está completamente consumido

---

## ⚠️ Validaciones Críticas

1. **El proceso debe tener padre**: Solo procesos hijos pueden consumir outputs del padre
2. **Output debe pertenecer al padre directo**: El output debe pertenecer al proceso padre del proceso que consume
3. **Consumo único**: Un proceso solo puede consumir un output una vez
4. **No exceder disponibilidad**: El consumo no puede exceder el output disponible
5. **Cascade delete**: Si se elimina el output o el proceso, se eliminan los consumos

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

