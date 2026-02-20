# Recepciones y Despachos - Despachos de Cebo (Cebo Dispatches)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `CeboDispatch` representa un **despacho de cebo** a un proveedor. Registra qué productos fueron despachados, en qué cantidades (peso neto), y a qué proveedor. Permite hacer seguimiento de las salidas de cebo del sistema.

**Archivo del modelo**: `app/Models/CeboDispatch.php`

**Relación con Inventario**: Los despachos registran la salida de productos, pero **NO eliminan automáticamente** palets o cajas del inventario. Son un registro contable/logístico.

**Nota**: "Cebo" se refiere a productos utilizados como cebo para la pesca.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `cebo_dispatches`

**Migración**: `database/migrations/companies/2024_08_15_124858_create_cebo_dispatches_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del despacho |
| `supplier_id` | bigint | NO | FK a `suppliers` - Proveedor receptor |
| `date` | date | NO | Fecha de despacho |
| `notes` | text | YES | Notas adicionales |
| `export_type` | string | YES | Tipo de exportación (en fillable pero no en migración) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign key a `suppliers`

**Constraints**:
- `supplier_id` → `suppliers.id` (onDelete: cascade)

**⚠️ Nota**: El campo `export_type` está en fillable del modelo pero **NO existe en la migración**.

### Tabla: `cebo_dispatch_products`

**Migración**: `database/migrations/companies/2024_08_15_125002_create_cebo_dispatch_products_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único |
| `dispatch_id` | bigint | NO | FK a `cebo_dispatches` |
| `product_id` | bigint | NO | FK a `products` |
| `net_weight` | decimal(6,2) | NO | Peso neto despachado |
| `price` | decimal | YES | Precio unitario (en fillable pero no en migración) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign keys a `cebo_dispatches` y `products`

**Constraints**:
- `dispatch_id` → `cebo_dispatches.id` (onDelete: cascade)
- `product_id` → `products.id` (onDelete: cascade)

**⚠️ Nota**: El campo `price` está en fillable del modelo pero **NO existe en la migración**.

---

## 📦 Modelo Eloquent

### CeboDispatch

#### Fillable Attributes

```php
protected $fillable = [
    'supplier_id',
    'date',
    'notes',
    'export_type', // ⚠️ No existe en BD
];
```

#### Appended Attributes

```php
protected $appends = ['net_weight', 'total_amount'];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

### CeboDispatchProduct

#### Fillable Attributes

```php
protected $fillable = [
    'dispatch_id',
    'product_id',
    'net_weight',
    'price', // ⚠️ No existe en BD
];
```

---

## 🔗 Relaciones

### CeboDispatch

#### 1. `supplier()` - Proveedor
```php
public function supplier()
{
    return $this->belongsTo(Supplier::class);
}
```

#### 2. `products()` - Productos Despachados
```php
public function products()
{
    return $this->hasMany(CeboDispatchProduct::class, 'dispatch_id');
}
```

### CeboDispatchProduct

#### 1. `dispatch()` - Despacho
```php
public function dispatch()
{
    return $this->belongsTo(CeboDispatch::class, 'dispatch_id');
}
```

#### 2. `product()` - Producto
```php
public function product()
{
    return $this->belongsTo(Product::class, 'product_id');
}
```

---

## 🔢 Accessors (Atributos Calculados)

### CeboDispatch

#### `getNetWeightAttribute()`

Suma el peso neto de todos los productos despachados.

```php
return $this->products->sum('net_weight');
```

#### `getTotalAmountAttribute()`

Calcula el total multiplicando peso neto por precio de cada producto.

```php
return $this->products->sum(function ($product) {
    return $product->net_weight * ($product->price ?? 0);
});
```

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/CeboDispatchController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Despachos
```php
GET /v2/cebo-dispatches
```

**Eager Loading**: `supplier`, `products.product`

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `suppliers`: Filtrar por proveedores (array de IDs)
- `dates[start]`: Fecha inicio
- `dates[end]`: Fecha fin
- `species`: Filtrar por especies (array de IDs) - Busca en productos
- `products`: Filtrar por productos (array de IDs)
- `notes`: Buscar por notas (LIKE)
- `export_type`: Filtrar por tipo de exportación (no implementado en filtro)

**Orden**: Por fecha descendente (más reciente primero)

**Query parameters**: `perPage` (default: 12)

**Respuesta**: Collection paginada de `CeboDispatchResource`

#### `store(Request $request)` - Crear Despacho
```php
POST /v2/cebo-dispatches
```

**Validación**:
```php
[
    'supplier.id' => 'required',
    'date' => 'required|date',
    'notes' => 'nullable|string',
    'export_type' => 'nullable|string|in:a3erp,facilcom',
    'exportType' => 'nullable|string|in:a3erp,facilcom',
    'details' => 'required|array',
    'details.*.product.id' => 'required|exists:tenant.products,id',
    'details.*.netWeight' => 'required|numeric',
    'details.*.price' => 'nullable|numeric|min:0',
]
```

**Request body**:
```json
{
    "supplier": { "id": 1 },
    "date": "2025-01-15",
    "notes": "Despacho normal",
    "exportType": "a3erp",
    "details": [
        {
            "product": { "id": 10 },
            "netWeight": 500.25,
            "price": 2.50
        }
    ]
}
```

**Comportamiento**:
- Crea el despacho. **Si no se envía** `export_type` ni `exportType`, el backend lo rellena con el del proveedor (`cebo_export_type`); si el proveedor no lo tiene, queda `null`.
- Crea los productos despachados (details). **Si un detalle no lleva precio** (o viene vacío), el backend lo rellena con el precio de la última salida de cebo de ese proveedor para ese producto; si no hay anterior, queda `null`.

#### `show($id)` - Mostrar Despacho
```php
GET /v2/cebo-dispatches/{id}
```

**Eager Loading**: `supplier`, `products.product`

#### `update(Request $request, $id)` - Actualizar Despacho
```php
PUT /v2/cebo-dispatches/{id}
```

**Validación**: Igual que `store()` (incluye `export_type` / `exportType` opcional).

**Comportamiento**:
- Actualiza el despacho. **Si no se envía** `export_type` ni `exportType`, el backend lo rellena con el del proveedor (`cebo_export_type`) o mantiene el actual si el proveedor no tiene tipo.
- **Elimina todos los productos** y los vuelve a crear. **Si un detalle no lleva precio**, el backend lo rellena con el de la última salida de cebo de ese proveedor para ese producto.
- **⚠️ No preserva IDs** de productos al actualizar

#### `destroy($id)` - Eliminar Despacho
```php
DELETE /v2/cebo-dispatches/{id}
```

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Despachos
```php
DELETE /v2/cebo-dispatches
```

**Body**:
```json
{
    "ids": [1, 2, 3]
}
```

---

## 📊 Estadísticas

**Controlador**: `app/Http/Controllers/v2/CeboDispatchStatisticsController.php`

**Servicio**: `app/Services/v2/CeboDispatchStatisticsService.php`

### `dispatchChartData(Request $request)` - Datos para Gráficos
```php
GET /v2/cebo-dispatches/dispatch-chart-data
```

**Query parameters**:
- `dateFrom`: Fecha inicio (YYYY-MM-DD) - **requerido**
- `dateTo`: Fecha fin (YYYY-MM-DD) - **requerido**
- `valueType`: `amount` o `quantity` - **requerido**
- `groupBy`: `day`, `week`, o `month` (default: `day`)
- `speciesId`: ID de especie (opcional)
- `familyId`: ID de familia (opcional)
- `categoryId`: ID de categoría (opcional)

**Respuesta**:
```json
[
    {
        "date": "2025-01-15",
        "value": 1234.56
    },
    ...
]
```

**Lógica**: Similar a `RawMaterialReceptionStatisticsService`
- Agrupa despachos por período (día/semana/mes)
- Filtra por especie/familia/categoría si se especifica
- Calcula `amount` (precio * peso) o `quantity` (peso total)
- Solo incluye períodos con datos

---

## 📤 Exportaciones

**Controlador**: `app/Http/Controllers/v2/ExcelController.php`

### Exportar a Facilcom
```php
GET /v2/cebo-dispatches/facilcom-xlsx
```

### Exportar a A3ERP
```php
GET /v2/cebo-dispatches/a3erp-xlsx
```

### Exportar a A3ERP (Versión 2)
```php
GET /v2/cebo-dispatches/a3erp2-xlsx
```

---

## 📄 API Resources

### CeboDispatchResource

**Archivo**: `app/Http/Resources/v2/CeboDispatchResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "supplier": {...},
    "date": "2025-01-15",
    "exportType": "a3erp",
    "notes": "...",
    "netWeight": 500.25,
    "details": [...]
}
```

### CeboDispatchProductResource

**Archivo**: `app/Http/Resources/v2/CeboDispatchProductResource.php`

**Campos expuestos**: Similar a `RawMaterialReceptionProductResource`

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/cebo-dispatches/*`

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Campo export_type No Existe en BD

1. **export_type en Fillable Pero No en Migración** (`app/Models/CeboDispatch.php:18`)
   - Campo `export_type` está en fillable pero no existe en la tabla
   - **Líneas**: 18, migración línea 14-21
   - **Problema**: No se puede guardar tipo de exportación
   - **Recomendación**: 
     - Agregar migración para crear campo `export_type`
     - O eliminar de fillable si no se necesita

### ⚠️ Campo price No Existe en BD

2. **price en Fillable Pero No en Migración** (`app/Models/CeboDispatchProduct.php:15`)
   - Campo `price` está en fillable pero no existe en la tabla
   - **Líneas**: 15, migración línea 14-22
   - **Problema**: No se puede guardar precio
   - **Recomendación**: 
     - Agregar migración para crear campo `price`
     - O eliminar de fillable si no se necesita

### ⚠️ Filtro export_type No Implementado

3. **Filtro export_type Vacío** (`app/Http/Controllers/v2/CeboDispatchController.php:64-66`)
   - Se verifica si existe `export_type` pero no se usa en el filtro
   - **Líneas**: 64-66
   - **Problema**: Filtro no funciona
   - **Recomendación**: Implementar filtro o eliminar

### ⚠️ Update Elimina y Recrea Productos

4. **Eliminación Completa de Productos** (`app/Http/Controllers/v2/CeboDispatchController.php:136`)
   - Elimina todos los productos y los vuelve a crear
   - **Líneas**: 136-142
   - **Problema**: 
     - Pierde IDs históricos
     - No es eficiente
   - **Recomendación**: Actualizar solo los que cambiaron

### ⚠️ Sin Validación de Precio en Store

5. **No Se Valida ni Guarda Precio** (`app/Http/Controllers/v2/CeboDispatchController.php:102-105`)
   - No valida ni guarda precios
   - **Problema**: No se puede registrar costos
   - **Recomendación**: Agregar validación y guardado de precios si se necesita

### ⚠️ Sin Validación de export_type en Store

6. **No Se Valida ni Guarda export_type** (`app/Http/Controllers/v2/CeboDispatchController.php:90-98`)
   - No valida ni guarda export_type aunque está en fillable
   - **Problema**: Campo no se puede usar
   - **Recomendación**: Agregar validación y guardado si se necesita

### ⚠️ Código Incompleto en Servicio de Estadísticas

7. **Línea Faltante en getDispatchChartData** (`app/Services/v2/CeboDispatchStatisticsService.php:32`)
   - Falta la línea que carga los despachos con eager loading
   - **Líneas**: 32-33
   - **Problema**: Código incompleto que puede causar errores
   - **Recomendación**: Completar código (similar a RawMaterialReceptionStatisticsService)

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

