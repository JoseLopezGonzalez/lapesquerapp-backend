# Producción - Entradas (ProductionInput)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `ProductionInput` representa una **entrada de producción**, es decir, una caja individual que se consume en un proceso de producción. Es la unidad mínima de trazabilidad: cada caja que entra en producción se registra individualmente.

**Archivo del modelo**: `app/Models/ProductionInput.php`

**Concepto clave**: Una entrada vincula una `Box` (caja física) a un `ProductionRecord` (proceso). El peso, producto y lote se obtienen automáticamente desde la caja.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `production_inputs`

**Migración**: `database/migrations/companies/2025_11_23_135215_create_production_inputs_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la entrada |
| `production_record_id` | bigint | NO | FK a `production_records` - Proceso que consume la caja |
| `box_id` | bigint | NO | FK a `boxes` - Caja individual consumida |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `production_record_id` (index)
- `box_id` (index)
- **Unique constraint**: `['production_record_id', 'box_id']` - Una caja no puede estar dos veces en el mismo proceso

**Constraints**:
- `production_record_id` → `production_records.id` (onDelete: cascade)
- `box_id` → `boxes.id` (onDelete: cascade)

**Nota importante**: El unique constraint previene que una caja sea asignada dos veces al mismo proceso, pero **no previene** que una caja sea consumida en múltiples procesos diferentes.

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'production_record_id',
    'box_id',
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
- Indica en qué proceso se consume la caja

### 2. `box()` - Caja Individual
```php
public function box()
{
    return $this->belongsTo(Box::class, 'box_id');
}
```
- Relación muchos-a-uno con `Box`
- La caja física que se consume

---

## 🎯 Attributes Calculados (Accessors)

Estos métodos permiten acceder a datos de la caja de forma transparente sin necesidad de cargar explícitamente la relación.

### `getProductAttribute()` - Producto de la Caja
```php
public function getProductAttribute()
{
    return $this->box->product ?? null;
}
```
- Retorna el producto asociado a la caja
- **Nota**: Requiere que `box` esté cargada

### `getLotAttribute()` - Lote de la Caja
```php
public function getLotAttribute()
{
    return $this->box->lot ?? null;
}
```
- Retorna el lote de la caja

### `getWeightAttribute()` - Peso de la Caja
```php
public function getWeightAttribute()
{
    return $this->box->net_weight ?? 0;
}
```
- Retorna el peso neto de la caja

### `getPalletAttribute()` - Palet de la Caja
```php
public function getPalletAttribute()
{
    return $this->box->pallet ?? null;
}
```
- Retorna el palet donde está la caja (si existe)
- **Nota**: Usa el accessor `pallet` del modelo `Box`

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/ProductionInputController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Entradas
```php
GET /v2/production-inputs
```

**Parámetros de query**:
- `production_record_id` (integer): Filtrar por proceso
- `box_id` (integer): Filtrar por caja específica
- `production_id` (integer): Filtrar por lote de producción (a través del record)

**Relaciones cargadas**: `productionRecord`, `box.product`

**⚠️ Nota importante**: Este endpoint **NO usa paginación**, retorna todos los resultados. Esto puede ser problemático con muchos registros (ver observaciones).

#### `store(Request $request)` - Crear Entrada
```php
POST /v2/production-inputs
```

**Validación**:
```php
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'box_id' => 'required|exists:tenant.boxes,id',
]
```

**Validaciones adicionales**:
- Verifica que la caja no esté ya asignada al mismo proceso (unique constraint)

**Comportamiento**:
- Crea la entrada
- Carga relaciones para respuesta

**Respuesta**: 201 con datos de la entrada creada

**⚠️ Problema**: No valida si la caja ya fue usada en otro proceso del mismo lote (ver observaciones)

#### `storeMultiple(Request $request)` - Crear Múltiples Entradas
```php
POST /v2/production-inputs/multiple
```

**Validación**:
```php
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'box_ids' => 'required|array',
    'box_ids.*' => 'required|exists:tenant.boxes,id',
]
```

**Comportamiento**:
- Crea múltiples entradas en una transacción
- Ignora cajas que ya están asignadas (no falla toda la operación)
- Retorna array de creadas y errores

**Transacción**: Usa `DB::beginTransaction()` para atomicidad

**Respuesta**:
```json
{
    "message": "3 entradas creadas correctamente.",
    "data": [...],
    "errors": ["La caja 5 ya está asignada a este proceso."]
}
```

**⚠️ Problema**: Los errores no incluyen ID de caja específico en el mensaje (ver observaciones)

#### `show(string $id)` - Mostrar Entrada
```php
GET /v2/production-inputs/{id}
```

**Relaciones cargadas**: `productionRecord`, `box.product`

#### `destroy(string $id)` - Eliminar Entrada
```php
DELETE /v2/production-inputs/{id}
```

**Comportamiento**: Elimina la entrada (cascade no afecta, solo es referencia)

**⚠️ Nota**: No hay método `update()` - las entradas son inmutables una vez creadas

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/ProductionInputResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "productionRecordId": 5,
    "boxId": 123,
    "box": {
        "id": 123,
        "lot": "LOT-2024-001",
        "netWeight": 25.50,
        "grossWeight": 26.00,
        "product": {
            "id": 10,
            "name": "Atún fresco"
        }
    },
    "product": {
        "id": 10,
        "name": "Atún fresco"
    },
    "lot": "LOT-2024-001",
    "weight": 25.50,
    "pallet": {
        "id": 45
    },
    "createdAt": "2024-01-15T11:30:00Z",
    "updatedAt": "2024-01-15T11:30:00Z"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/production-inputs/*`

---

## 📝 Ejemplos de Uso

### Crear una Entrada
```http
POST /v2/production-inputs
Content-Type: application/json
X-Tenant: empresa1

{
    "production_record_id": 5,
    "box_id": 123
}
```

### Crear Múltiples Entradas
```http
POST /v2/production-inputs/multiple
Content-Type: application/json

{
    "production_record_id": 5,
    "box_ids": [123, 124, 125, 126]
}
```

### Listar Entradas de un Proceso
```http
GET /v2/production-inputs?production_record_id=5
```

### Listar Todas las Entradas de un Lote
```http
GET /v2/production-inputs?production_id=10
```

### Verificar si una Caja Está Disponible

A través del modelo `Box`:
```php
$box = Box::find(123);
if ($box->isAvailable) {
    // La caja no ha sido usada en ningún proceso
}
```

---

## 🔍 Conceptos Importantes

### Trazabilidad por Caja

Cada entrada vincula una caja específica a un proceso. Esto permite:
- Rastrear exactamente qué cajas se consumieron en cada proceso
- Calcular pesos exactos desde los datos de las cajas
- Mantener trazabilidad total desde materia prima hasta producto final

### Disponibilidad de Cajas

El modelo `Box` tiene un accessor `isAvailable` que verifica si la caja ha sido usada en producción:
```php
public function getIsAvailableAttribute()
{
    return !$this->productionInputs()->exists();
}
```

**Nota**: Una caja puede estar disponible para un proceso pero no disponible si ya fue usada en otro proceso del mismo lote. La validación actual solo previene duplicados dentro del mismo proceso.

### Unique Constraint

El constraint `['production_record_id', 'box_id']` garantiza que:
- Una caja no puede estar dos veces en el mismo proceso
- Pero una caja **sí puede** estar en múltiples procesos diferentes

Esta decisión permite que una caja se consuma parcialmente en un proceso y luego el resto en otro proceso (aunque esto requeriría lógica adicional de "cajas parciales" que no está implementada).

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Validaciones Faltantes

1. **No Validar Disponibilidad a Nivel de Lote** (`app/Http/Controllers/v2/ProductionInputController.php:46-73`)
   - Solo valida duplicados dentro del mismo proceso
   - **Líneas**: 54-62
   - **Problema**: Una caja podría consumirse múltiples veces en diferentes procesos del mismo lote
   - **Recomendación**: Validar disponibilidad a nivel de lote completo:
     ```php
     $productionId = ProductionRecord::find($validated['production_record_id'])->production_id;
     $alreadyUsed = ProductionInput::whereHas('productionRecord', function($q) use ($productionId) {
         $q->where('production_id', $productionId);
     })->where('box_id', $validated['box_id'])->exists();
     ```

2. **No Validar Estado del Proceso** (`app/Http/Controllers/v2/ProductionInputController.php:46-73`)
   - No valida si el proceso está finalizado antes de agregar entradas
   - **Líneas**: 46-73
   - **Problema**: Pueden agregarse entradas a procesos finalizados
   - **Recomendación**: Validar `$record->isCompleted()` antes de crear

3. **No Validar Estado del Lote** (`app/Http/Controllers/v2/ProductionInputController.php:46-73`)
   - No valida si el lote está abierto
   - **Líneas**: 46-73
   - **Problema**: Pueden agregarse entradas a lotes cerrados
   - **Recomendación**: Validar `$production->isOpen()`

### ⚠️ Performance: Falta de Paginación

4. **Index Sin Paginación** (`app/Http/Controllers/v2/ProductionInputController.php:16-41`)
   - `index()` retorna todos los resultados con `get()` sin paginación
   - **Líneas**: 40
   - **Problema**: Con muchos registros puede causar timeout o memoria insuficiente
   - **Recomendación**: Implementar paginación:
     ```php
     $perPage = $request->input('perPage', 50);
     return ProductionInputResource::collection($query->paginate($perPage));
     ```

### ⚠️ Mensajes de Error Poco Informativos

5. **Mensaje de Error Genérico** (`app/Http/Controllers/v2/ProductionInputController.php:58-62`)
   - Mensaje "La caja ya está asignada a este proceso" no incluye ID de caja
   - **Líneas**: 59-61
   - **Problema**: Difícil identificar cuál caja causa el error
   - **Recomendación**: Incluir ID en mensaje:
     ```php
     "La caja {$validated['box_id']} ya está asignada a este proceso."
     ```

6. **Errores en storeMultiple sin ID Específico** (`app/Http/Controllers/v2/ProductionInputController.php:97-99`)
   - Mensaje solo dice "La caja {$boxId}" pero el array de errores no identifica claramente cuál
   - **Líneas**: 98
   - **Problema**: Si hay múltiples errores, difícil identificar cuáles cajas fallaron
   - **Recomendación**: Retornar estructura más detallada:
     ```php
     $errors[] = [
         'box_id' => $boxId,
         'message' => "La caja ya está asignada a este proceso."
     ];
     ```

### ⚠️ Transacciones y Manejo de Errores

7. **storeMultiple Continúa con Errores** (`app/Http/Controllers/v2/ProductionInputController.php:78-125`)
   - Si algunas cajas fallan, continúa con las demás
   - **Líneas**: 78-125
   - **Comportamiento actual**: Parcialmente correcto (ignora errores), pero puede ser confuso
   - **Recomendación**: 
     - Documentar claramente este comportamiento
     - O agregar opción `strict=true` para fallar si hay algún error

8. **Falta Validación de Box Disponible** (`app/Http/Controllers/v2/ProductionInputController.php:46-73`)
   - No verifica si la caja existe o está disponible antes de asignar
   - **Líneas**: 46-73
   - **Problema**: Aunque `exists` valida existencia, no valida disponibilidad
   - **Recomendación**: Agregar validación custom:
     ```php
     'box_id' => [
         'required',
         'exists:tenant.boxes,id',
         function ($attribute, $value, $fail) {
             $box = Box::find($value);
             if (!$box || !$box->isAvailable) {
                 $fail('La caja no está disponible.');
             }
         }
     ]
     ```

### ⚠️ Accessors con Relaciones No Cargadas

9. **Accessors Asumen Relación Cargada** (`app/Models/ProductionInput.php:38-65`)
   - `getProductAttribute()`, `getLotAttribute()`, etc. asumen que `box` está cargada
   - **Líneas**: 38-49, 51-57, 59-65
   - **Problema**: Si no está cargada, hace query N+1
   - **Recomendación**: 
     - Documentar que requiere eager loading
     - O cargar automáticamente en `boot()`
     - O usar `$this->relationLoaded('box')` para verificar

### ⚠️ Falta de Método Update

10. **No Hay Método Update** (`app/Http/Controllers/v2/ProductionInputController.php`)
    - No hay endpoint `PUT /v2/production-inputs/{id}`
    - **Problema**: Si se asigna una caja incorrecta, solo puede eliminarse y recrearse
    - **Recomendación**: 
      - Si es intencional (inmutabilidad), documentar claramente
      - O implementar update si es necesario para el negocio

### ⚠️ Falta de Validación de Integridad

11. **No Validar Box Pertenece a Mismo Tenant** (`app/Http/Controllers/v2/ProductionInputController.php:46-73`)
    - La validación `exists:tenant.boxes,id` valida tenant implícitamente
    - Pero si hay problemas de configuración, podría haber issues
    - **Recomendación**: Asegurar que el middleware tenant esté correctamente configurado (ya está, pero importante mencionar)

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

