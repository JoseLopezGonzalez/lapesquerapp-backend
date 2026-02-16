# Producción - Salidas (ProductionOutput)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `ProductionOutput` representa una **salida de producción**, es decir, un producto producido en un proceso. A diferencia de las entradas que vinculan cajas físicas específicas, las salidas declaran "cuánto se produjo" de manera agregada (cantidad de cajas y peso total).

**Archivo del modelo**: `app/Models/ProductionOutput.php`

**Concepto clave**: Las salidas declaran la producción teórica. Las cajas físicas reales se crean posteriormente en el módulo de stock cuando se crean palets con esas cajas.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `production_outputs`

**Migración**: `database/migrations/companies/2025_11_23_135217_create_production_outputs_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la salida |
| `production_record_id` | bigint | NO | FK a `production_records` - Proceso que produce |
| `product_id` | bigint | NO | FK a `products` - Producto producido |
| `lot_id` | string | YES | Identificador del lote del producto (opcional) |
| `boxes` | integer | NO | Cantidad de cajas producidas (default: 0) |
| `weight_kg` | decimal(10,2) | NO | Peso total en kilogramos (default: 0) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `production_record_id` (index)
- `product_id` (index)
- `lot_id` (index)

**Constraints**:
- `production_record_id` → `production_records.id` (onDelete: cascade)
- `product_id` → `products.id` (onDelete: cascade)

**Nota importante**: `lot_id` es un string opcional que puede diferir del lote del `Production`. Esto permite que un proceso produzca productos con diferentes lotes.

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'production_record_id',
    'product_id',
    'lot_id',
    'boxes',
    'weight_kg',
];
```

### Casts

```php
protected $casts = [
    'boxes' => 'integer',
    'weight_kg' => 'decimal:2',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `productionRecord()` - Proceso de Producción
```php
public function productionRecord()
{
    return $this->belongsTo(ProductionRecord::class, 'production_record_id');
}
```
- Relación muchos-a-uno con `ProductionRecord`
- Indica en qué proceso se produjo

### 2. `product()` - Producto Producido
```php
public function product()
{
    return $this->belongsTo(Product::class, 'product_id');
}
```
- Relación muchos-a-uno con `Product`
- El producto final producido

---

## 🎯 Attributes Calculados (Accessors)

### `getAverageWeightPerBoxAttribute()` - Peso Promedio por Caja
```php
public function getAverageWeightPerBoxAttribute()
{
    if ($this->boxes > 0) {
        return $this->weight_kg / $this->boxes;
    }
    return 0;
}
```
- Calcula el peso promedio por caja
- **Validación**: Maneja división por cero retornando 0 si `boxes` es 0

**Ejemplo**:
- `weight_kg` = 100.50
- `boxes` = 5
- `average_weight_per_box` = 20.10 kg

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/ProductionOutputController.php`

### Métodos del Controlador

**Nota**: Para sincronizar todas las salidas de un proceso (crear/actualizar/eliminar), use el método `syncOutputs()` en `ProductionRecordController` (ver sección de ejemplos).

#### `index(Request $request)` - Listar Salidas
```php
GET /v2/production-outputs
```

**Parámetros de query**:
- `production_record_id` (integer): Filtrar por proceso
- `product_id` (integer): Filtrar por producto
- `lot_id` (string): Filtrar por lote del producto
- `production_id` (integer): Filtrar por lote de producción (a través del record)
- `perPage` (integer, default: 15): Cantidad por página

**Relaciones cargadas**: `productionRecord`, `product`

**Ordenamiento**: Por defecto (probablemente `created_at`)

#### `store(Request $request)` - Crear Salida
```php
POST /v2/production-outputs
```

**Validación**:
```php
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'product_id' => 'required|exists:tenant.products,id',
    'lot_id' => 'nullable|string',
    'boxes' => 'required|integer|min:0',
    'weight_kg' => 'required|numeric|min:0',
]
```

**Comportamiento**:
- Crea la salida
- Carga relaciones para respuesta

**Respuesta**: 201 con datos de la salida creada

**⚠️ Problema**: No valida consistencia de `lot_id` con el lote del `Production` (ver observaciones)

#### `show(string $id)` - Mostrar Salida
```php
GET /v2/production-outputs/{id}
```

**Relaciones cargadas**: `productionRecord`, `product`

#### `update(Request $request, string $id)` - Actualizar Salida
```php
PUT /v2/production-outputs/{id}
```

**Validación**: Misma que `store()` pero con `sometimes` en lugar de `required`

**Comportamiento**:
- Actualiza la salida
- Carga relaciones para respuesta

**⚠️ Problema**: Permite cambiar `production_record_id`, lo que podría romper cálculos (ver observaciones)

#### `destroy(string $id)` - Eliminar Salida
```php
DELETE /v2/production-outputs/{id}
```

**Comportamiento**: Elimina la salida (cascade no afecta, solo es referencia)

#### `storeMultiple(Request $request)` - Crear Múltiples Salidas
```php
POST /v2/production-outputs/multiple
```

**Validación**:
```php
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'outputs' => 'required|array|min:1',
    'outputs.*.product_id' => 'required|exists:tenant.products,id',
    'outputs.*.lot_id' => 'nullable|string',
    'outputs.*.boxes' => 'required|integer|min:0',
    'outputs.*.weight_kg' => 'required|numeric|min:0',
]
```

**Comportamiento**:
- Crea múltiples salidas en una transacción
- Retorna array de salidas creadas y errores (si los hay)
- Si alguna falla, las que se crearon exitosamente se mantienen

**Respuesta**: 201 con array de salidas creadas y errores

**Ejemplo de request**:
```json
{
    "production_record_id": 123,
    "outputs": [
        {
            "product_id": 10,
            "lot_id": "LOT-001",
            "boxes": 20,
            "weight_kg": 300.00
        },
        {
            "product_id": 11,
            "boxes": 15,
            "weight_kg": 225.00
        }
    ]
}
```

**Ejemplo de response**:
```json
{
    "message": "2 salida(s) creada(s) correctamente.",
    "data": [...],
    "errors": []
}
```

**Nota**: Este endpoint es útil para crear múltiples salidas de una vez, pero para editar todas las salidas de un proceso, use el endpoint de sincronización en `ProductionRecordController`.

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/ProductionOutputResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "productionRecordId": 5,
    "productId": 10,
    "product": {
        "id": 10,
        "name": "Filetes de atún en conserva"
    },
    "lotId": "LOT-2024-001-OUT",
    "boxes": 8,
    "weightKg": 120.50,
    "averageWeightPerBox": 15.0625,
    "createdAt": "2024-01-15T12:00:00Z",
    "updatedAt": "2024-01-15T12:00:00Z"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/production-outputs/*`

---

## 📝 Ejemplos de Uso

### Crear una Salida
```http
POST /v2/production-outputs
Content-Type: application/json
X-Tenant: empresa1

{
    "production_record_id": 5,
    "product_id": 10,
    "lot_id": "LOT-2024-001-OUT",
    "boxes": 8,
    "weight_kg": 120.50
}
```

### Listar Salidas de un Proceso
```http
GET /v2/production-outputs?production_record_id=5
```

### Listar Todas las Salidas de un Lote
```http
GET /v2/production-outputs?production_id=10
```

### Actualizar una Salida
```http
PUT /v2/production-outputs/1
Content-Type: application/json

{
    "boxes": 9,
    "weight_kg": 135.75
}
```

### Crear Múltiples Salidas
```http
POST /v2/production-outputs/multiple
Content-Type: application/json

{
    "production_record_id": 123,
    "outputs": [
        {
            "product_id": 10,
            "lot_id": "LOT-001",
            "boxes": 20,
            "weight_kg": 300.00
        },
        {
            "product_id": 11,
            "boxes": 15,
            "weight_kg": 225.00
        }
    ]
}
```

### Sincronizar Todas las Salidas de un Proceso
```http
PUT /v2/production-records/123/outputs
Content-Type: application/json

{
    "outputs": [
        {
            "id": 456,
            "product_id": 10,
            "lot_id": "LOT-001-UPDATED",
            "boxes": 25,
            "weight_kg": 375.00
        },
        {
            "product_id": 12,
            "lot_id": "LOT-003",
            "boxes": 10,
            "weight_kg": 150.00
        }
    ]
}
```

**Nota**: Este endpoint permite crear (sin `id`), actualizar (con `id`) y eliminar (no incluir en el array) todas las salidas de un proceso en una sola petición. **Recomendado para editar todas las salidas de una vez.**

---

## 🔍 Conceptos Importantes

### Salidas vs Cajas Físicas

**Salida (`ProductionOutput`)**:
- Declara cuánto se produjo
- Agregado: "8 cajas, 120.50 kg"
- No vincula cajas físicas específicas

**Cajas Físicas (`Box`)**:
- Se crean posteriormente en el módulo de stock
- Cada caja tiene ID único, GS1-128, peso exacto
- Se agrupan en palets

**Relación**: Las cajas físicas deben coincidir con las salidas declaradas. Esto se valida en la conciliación (`Production::reconcile()`).

### Lot_id Opcional

El campo `lot_id` permite que:
- Un proceso produzca productos con diferentes lotes
- Se mantenga trazabilidad si un producto tiene lote diferente al lote del `Production`

**Ejemplo**:
- `Production.lot` = "LOT-2024-001"
- `ProductionOutput.lot_id` = "LOT-2024-001-CONSERVA" (sub-lote)

### Múltiples Salidas del Mismo Producto

Un proceso puede tener múltiples `ProductionOutput` del mismo `product_id`:
- Permite registrar producciones parciales
- O diferentes lotes del mismo producto en el mismo proceso

**Ejemplo**:
```
Process: "Envasado"
- Output 1: product_id=10, boxes=5, lot_id="LOT-A"
- Output 2: product_id=10, boxes=3, lot_id="LOT-B"
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Validaciones Faltantes

1. **No Validar Consistencia de lot_id** (`app/Http/Controllers/v2/ProductionOutputController.php:51-70`)
   - `lot_id` es opcional y puede diferir del lote del `Production`
   - **Líneas**: 56
   - **Problema**: No hay validación de que `lot_id` tenga sentido o formato válido
   - **Recomendación**: 
     - Agregar validación de formato si es necesario
     - O documentar claramente el propósito de `lot_id`
     - O validar que coincida con `Production.lot` si debe coincidir

2. **No Validar Estado del Proceso** (`app/Http/Controllers/v2/ProductionOutputController.php:51-70`)
   - No valida si el proceso está finalizado antes de agregar salidas
   - **Líneas**: 51-70
   - **Problema**: Pueden agregarse salidas a procesos finalizados
   - **Recomendación**: Validar `$record->isCompleted()` antes de crear

3. **No Validar Estado del Lote** (`app/Http/Controllers/v2/ProductionOutputController.php:51-70`)
   - No valida si el lote está abierto
   - **Líneas**: 51-70
   - **Problema**: Pueden agregarse salidas a lotes cerrados
   - **Recomendación**: Validar `$production->isOpen()`

4. **No Validar Lógica de Negocio** (`app/Http/Controllers/v2/ProductionOutputController.php:51-70`)
   - No valida que `boxes` y `weight_kg` sean consistentes
   - **Líneas**: 57-58
   - **Problema**: Pueden crearse salidas con `boxes=0` y `weight_kg>0`, o viceversa
   - **Recomendación**: 
     - Validar que si `boxes > 0`, entonces `weight_kg > 0`
     - O validar que `average_weight_per_box` esté en rango razonable

### ⚠️ Update Permite Cambiar production_record_id

5. **Update Permite Cambiar Proceso** (`app/Http/Controllers/v2/ProductionOutputController.php:89-110`)
   - `update()` permite cambiar `production_record_id`
   - **Líneas**: 94
   - **Problema**: Puede mover una salida a otro proceso, rompiendo cálculos
   - **Recomendación**: 
     - No permitir cambiar `production_record_id` en update
     - O validar que el proceso destino pertenezca al mismo lote

### ⚠️ División por Cero

6. **AverageWeightPerBox Maneja División por Cero** (`app/Models/ProductionOutput.php:46-52`)
   - Valida correctamente división por cero
   - **Líneas**: 48-51
   - **Estado**: Correcto, pero podría validar también si `weight_kg < 0`

### ⚠️ Validaciones de Rango

7. **Min:0 Pero No Máximo** (`app/Http/Controllers/v2/ProductionOutputController.php:57-58`)
   - Valida `min:0` pero no hay máximo
   - **Líneas**: 57-58
   - **Problema**: Pueden crearse valores extremadamente altos
   - **Recomendación**: Agregar validación de máximo razonable si es necesario para el negocio

### ⚠️ Falta de Validación de Producto

8. **No Validar Tipo de Producto** (`app/Http/Controllers/v2/ProductionOutputController.php:51-70`)
   - No valida que el producto sea del tipo correcto para producción
   - **Líneas**: 55
   - **Problema**: Podrían asignarse productos incorrectos
   - **Recomendación**: Validar si el producto tiene atributos específicos necesarios

### ⚠️ Relación con Boxes Físicas

9. **No Hay Relación Directa con Boxes** (`app/Models/ProductionOutput.php`)
   - `ProductionOutput` no tiene relación con las cajas físicas creadas
   - **Problema**: Dificulta validar que las cajas físicas coincidan con las salidas
   - **Recomendación**: 
     - Si es necesario, agregar tabla intermedia `production_output_boxes`
     - O validar en conciliación usando `Box.lot` coincidente

### ⚠️ Performance: Posibles Queries N+1

10. **Falta Eager Loading por Defecto** (`app/Http/Controllers/v2/ProductionOutputController.php:15-46`)
    - `index()` carga relaciones, pero si se accede a `average_weight_per_box` desde resource, está bien
    - **Estado**: Correcto, pero importante documentar que requiere eager loading de `product`

### ⚠️ lot_id como String Sin Validación

11. **lot_id Sin Formato** (`database/migrations/companies/2025_11_23_135217_create_production_outputs_table.php:18`)
    - `lot_id` es string sin restricciones de formato
    - **Problema**: Puede tener cualquier valor, dificultando búsquedas y conciliaciones
    - **Recomendación**: 
      - Agregar validación de formato si hay estándar
      - O documentar claramente el formato esperado

### ⚠️ Múltiples Salidas del Mismo Producto

12. **Permite Duplicados Sin Restricción** (`database/migrations/companies/2025_11_23_135217_create_production_outputs_table.php`)
    - No hay unique constraint en `['production_record_id', 'product_id', 'lot_id']`
    - **Problema**: Pueden crearse múltiples salidas idénticas accidentalmente
    - **Recomendación**: 
      - Si no debe haber duplicados, agregar unique constraint
      - O documentar que los duplicados son intencionales (producciones parciales)

### ⚠️ Cálculo de Totales Depende de Salidas

13. **Totales del Proceso Dependen de Outputs** (`app/Models/ProductionRecord.php:108-111`)
    - `getTotalOutputWeightAttribute()` suma todos los outputs
    - **Problema**: Si hay outputs incorrectos, los totales serán incorrectos
    - **Recomendación**: Validar coherencia en creación/actualización

---

---

## 🔗 Endpoints Adicionales en ProductionRecordController

Para sincronizar todas las salidas de un proceso (crear/actualizar/eliminar en una sola petición), use:

**`syncOutputs(Request $request, string $id)`**
- Ruta: `PUT /v2/production-records/{id}/outputs`
- Permite crear (sin `id`), actualizar (con `id`) y eliminar (no incluir en el array) todas las salidas
- Valida que no se eliminen salidas con consumos asociados
- Usa transacciones para garantizar consistencia
- **Recomendado para editar todas las salidas de una vez**

**Última actualización**: Documentación actualizada con nuevos endpoints para múltiples salidas (2025-01-XX).

